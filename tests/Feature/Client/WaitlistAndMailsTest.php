<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Mail\EnrollmentOpenedMail;
use App\Mail\EnrollmentSubmittedMail;
use App\Mail\PaymentSettledMail;
use App\Mail\WaitlistPromotedMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Paczka 2: lista oczekujących na pojedyncze terminy, maile, „jak w poprzednim miesiącu”. */
class WaitlistAndMailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        Mail::fake();
    }

    private function client(string $email): Client
    {
        $client = Client::factory()->create(['invitation_used_at' => now()]);
        $client->user->update(['email' => $email]);
        $client->user->assignRole('client');

        return $client->fresh();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function occurrence(string $date, int $capacity = 20, string $time = '19:00'): ClassSchedule
    {
        $group = ClassGroup::factory()->forMonth(CarbonImmutable::parse($date)->startOfMonth())
            ->create(['start_time' => $time, 'capacity' => $capacity]);

        return ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => $date, 'start_time' => $time]);
    }

    private function withCredit(Client $client): MakeupCredit
    {
        $membership = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-11-01']);

        return MakeupCredit::factory()->for($client)->create([
            'source_reservation_id' => Reservation::factory()->released()->create([
                'client_id' => $client->id,
                'membership_id' => $membership->id,
                'class_schedule_id' => $this->occurrence('2026-11-03')->id,
            ])->id,
        ]);
    }

    private function confirmedSeat(ClassSchedule $occurrence): Reservation
    {
        $holder = $this->client('holder'.uniqid().'@wp.pl');
        $membership = Membership::factory()->create(['client_id' => $holder->id, 'start_date' => '2026-11-01']);

        return Reservation::factory()->create([
            'client_id' => $holder->id,
            'membership_id' => $membership->id,
            'class_schedule_id' => $occurrence->id,
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    public function test_full_class_puts_client_on_waitlist_and_she_is_promoted_with_mail_when_a_seat_frees(): void
    {
        $occurrence = $this->occurrence('2026-11-12', capacity: 1);
        $holder = $this->confirmedSeat($occurrence);

        $client = $this->client('anna@wp.pl');
        $credit = $this->withCredit($client);

        $this->actingAs($client->user)->post(route('client.free-spots.store', $occurrence))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'liście oczekujących'));

        $waiting = Reservation::where('client_id', $client->id)->where('class_schedule_id', $occurrence->id)->sole();
        $this->assertSame(ReservationStatus::Waitlist, $waiting->status);
        $this->assertSame(ReservationKind::Makeup, $waiting->kind);
        $this->assertFalse($credit->fresh()->used); // odrobienie jeszcze nie zużyte

        // ktoś zwalnia miejsce → wskakuje, odrobienie się zużywa, mail
        $this->actingAs($holder->client->user)->patch(route('client.reservations.cancel', $holder));

        $waiting->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $waiting->status);
        $this->assertSame($credit->id, $waiting->makeup_credit_id);
        $this->assertTrue($credit->fresh()->used);
        Mail::assertSent(WaitlistPromotedMail::class, fn ($mail) => $mail->hasTo('anna@wp.pl'));
    }

    public function test_waitlisted_client_without_credit_left_is_skipped_and_next_one_gets_the_seat(): void
    {
        $occurrence = $this->occurrence('2026-11-12', capacity: 1);
        $holder = $this->confirmedSeat($occurrence);

        $first = $this->client('first@wp.pl');
        $this->withCredit($first);
        $second = $this->client('second@wp.pl');
        $this->withCredit($second);

        $this->actingAs($first->user)->post(route('client.free-spots.store', $occurrence));
        $this->travel(1)->minutes();
        $this->actingAs($second->user)->post(route('client.free-spots.store', $occurrence));

        // pierwsza wykorzystuje odrobienie gdzie indziej → wypada z listy
        $other = $this->occurrence('2026-11-13');
        $this->actingAs($first->user)->post(route('client.free-spots.store', $other));
        $this->assertSame(ReservationStatus::Released, Reservation::where('client_id', $first->id)->where('class_schedule_id', $occurrence->id)->sole()->status);

        $this->actingAs($holder->client->user)->patch(route('client.reservations.cancel', $holder));

        $this->assertSame(ReservationStatus::Confirmed, Reservation::where('client_id', $second->id)->where('class_schedule_id', $occurrence->id)->sole()->status);
        Mail::assertSent(WaitlistPromotedMail::class, fn ($mail) => $mail->hasTo('second@wp.pl'));
        Mail::assertNotSent(WaitlistPromotedMail::class, fn ($mail) => $mail->hasTo('first@wp.pl'));
    }

    public function test_client_can_leave_the_waitlist(): void
    {
        $occurrence = $this->occurrence('2026-11-12', capacity: 1);
        $this->confirmedSeat($occurrence);
        $client = $this->client('anna@wp.pl');
        $this->withCredit($client);

        $this->actingAs($client->user)->post(route('client.free-spots.store', $occurrence));
        $waiting = Reservation::where('client_id', $client->id)->where('class_schedule_id', $occurrence->id)->sole();

        $this->actingAs($client->user)->get(route('client.classes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('membership.reservations.1.leavable', true));

        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $waiting))
            ->assertSessionHas('success', 'Wypisano Cię z listy oczekujących.');

        $this->assertSame(ReservationStatus::Released, $waiting->fresh()->status);
        $this->assertSame(1, $client->makeupCredits()->available()->count());
    }

    public function test_enrollment_mail_goes_to_real_address_only(): void
    {
        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();
        MembershipType::factory()->create(['sessions_per_week' => 1, 'name' => 'Zamknięty 1x/tydzień — miesięczny', 'price' => 100]);
        $group = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->create(['weekday' => 3, 'start_time' => '19:00']);
        ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-12-02', 'start_time' => '19:00']);

        $real = $this->client('anna@wp.pl');
        $this->actingAs($real->user)->post(route('client.enrollment.store'), ['month' => '2026-12', 'class_group_ids' => [$group->id], 'absences' => []])
            ->assertRedirect();

        Mail::assertSent(EnrollmentSubmittedMail::class, function (EnrollmentSubmittedMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('anna@wp.pl')
                && str_contains($html, '100,00 zł')
                && str_contains($html, 'do 13 listopada');
        });

        $placeholder = $this->client('ewa.nowak@test.pl');
        $this->actingAs($placeholder->user)->post(route('client.enrollment.store'), ['month' => '2026-12', 'class_group_ids' => [$group->id], 'absences' => []]);
        Mail::assertSent(EnrollmentSubmittedMail::class, 1);
    }

    public function test_payment_settled_mail_is_sent_once(): void
    {
        $client = $this->client('anna@wp.pl');
        $membership = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-11-01']);
        $payment = Payment::factory()->create(['membership_id' => $membership->id, 'client_id' => $client->id, 'status' => PaymentStatus::Pending]);

        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.payments.status', $payment), ['status' => 'zaksiegowana']);
        $this->actingAs($admin)->patch(route('admin.payments.status', $payment->fresh()), ['status' => 'zaksiegowana']);

        Mail::assertSent(PaymentSettledMail::class, 1);
        Mail::assertSent(PaymentSettledMail::class, fn ($mail) => $mail->hasTo('anna@wp.pl'));
    }

    public function test_enrollment_opened_notification_goes_once_to_activated_real_addresses(): void
    {
        $this->client('anna@wp.pl');
        $this->client('ewa.nowak@test.pl');                               // adres zastępczy
        $inactive = $this->client('nowa@wp.pl');
        $inactive->update(['invitation_used_at' => null]);                // bez aktywnego konta

        $admin = $this->admin();

        // zapisy zamknięte → nie wysyła
        $this->actingAs($admin)->post(route('admin.schedule.enrollment.notify'), ['month' => '2026-12'])
            ->assertSessionHas('error', 'Najpierw otwórz zapisy na ten miesiąc.');

        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();

        $this->actingAs($admin)->get(route('admin.schedule.index', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('notification.recipients', 1));

        $this->actingAs($admin)->post(route('admin.schedule.enrollment.notify'), ['month' => '2026-12'])
            ->assertSessionHas('success', 'Wysłano powiadomienie o zapisach na grudzień do 1 klientek.');
        $this->actingAs($admin)->post(route('admin.schedule.enrollment.notify'), ['month' => '2026-12'])
            ->assertSessionHas('error', 'Powiadomienie o tych zapisach zostało już wysłane.');

        Mail::assertSent(EnrollmentOpenedMail::class, 1);
        Mail::assertSent(EnrollmentOpenedMail::class, fn ($mail) => $mail->hasTo('anna@wp.pl'));
    }

    public function test_previous_month_plan_is_matched_to_the_new_schedule(): void
    {
        $client = $this->client('anna@wp.pl');
        $nov = CarbonImmutable::parse('2026-11-01');
        $dec = CarbonImmutable::parse('2026-12-01');

        // listopadowy grafik zamknięty z końcem listopada (jak w prawdziwych danych)
        $novPump = ClassGroup::factory()->forMonth($nov)->create(['weekday' => 2, 'start_time' => '17:55', 'active_to' => '2026-11-01']);
        $novHiit = ClassGroup::factory()->forMonth($nov)->create(['weekday' => 3, 'start_time' => '17:55', 'active_to' => '2026-11-01']);
        $decPump = ClassGroup::factory()->forMonth($dec)->create(['weekday' => 2, 'start_time' => '17:55', 'class_type_id' => $novPump->class_type_id]);
        ClassGroup::factory()->forMonth($dec)->create(['weekday' => 3, 'start_time' => '18:30', 'class_type_id' => $novHiit->class_type_id]); // przesunięte
        ClassSchedule::factory()->create(['class_group_id' => $decPump->id, 'date' => '2026-12-01', 'start_time' => '17:55']);

        $membership = Membership::factory()->create(['client_id' => $client->id, 'start_date' => $nov->toDateString()]);
        $membership->classGroups()->attach([$novPump->id, $novHiit->id]);

        $this->actingAs($client->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('previous.month_label', 'listopad')
                ->where('previous.group_ids', [$decPump->id])
                ->has('previous.missing', 1));
    }
}

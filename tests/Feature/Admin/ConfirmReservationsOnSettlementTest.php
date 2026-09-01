<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmReservationsOnSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  list<string>  $dates
     * @return list<ClassSchedule>
     */
    private function occurrences(ClassGroup $group, array $dates): array
    {
        return array_map(
            fn (string $date): ClassSchedule => ClassSchedule::factory()->create([
                'class_group_id' => $group->id,
                'date' => $date,
            ]),
            $dates,
        );
    }

    private function pendingReservation(Membership $membership, ClassSchedule $occurrence): Reservation
    {
        return Reservation::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'class_schedule_id' => $occurrence->id,
            'status' => ReservationStatus::PendingPayment,
            'confirmed_at' => null,
        ]);
    }

    public function test_settling_a_payment_confirms_the_memberships_pending_reservations(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 20]);
        [$first, $second] = $this->occurrences($group, ['2026-09-07', '2026-09-14']);

        $membership = Membership::factory()->create(['start_date' => '2026-09-01']);
        $rFirst = $this->pendingReservation($membership, $first);
        $rSecond = $this->pendingReservation($membership, $second);

        $payment = Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Settled->value])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();

        $this->assertSame(ReservationStatus::Confirmed, $rFirst->refresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $rSecond->refresh()->status);
        $this->assertSame(
            $payment->settled_date->toDateString(),
            $rFirst->confirmed_at->toDateString(),
        );
    }

    public function test_reservation_goes_to_waitlist_when_the_occurrence_is_full(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 1]);
        [$occurrence] = $this->occurrences($group, ['2026-09-07']);

        // ktoś inny zajął jedyne miejsce (już potwierdzony)
        Reservation::factory()->create([
            'class_schedule_id' => $occurrence->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => CarbonImmutable::parse('2026-09-02'),
        ]);

        $membership = Membership::factory()->create(['start_date' => '2026-09-01']);
        $reservation = $this->pendingReservation($membership, $occurrence);

        $payment = Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Settled->value]);

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Waitlist, $reservation->status);
        // data potwierdzenia zapisana mimo waitlisty — decyduje o kolejności
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertSame(
            $payment->refresh()->settled_date->toDateString(),
            $reservation->confirmed_at->toDateString(),
        );
    }

    public function test_earlier_dates_take_the_seats_first(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 1]);
        [$early, $late] = $this->occurrences($group, ['2026-09-07', '2026-09-21']);
        // wypełnij tylko późniejszy termin
        Reservation::factory()->create([
            'class_schedule_id' => $late->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $membership = Membership::factory()->create(['start_date' => '2026-09-01']);
        $rEarly = $this->pendingReservation($membership, $early);
        $rLate = $this->pendingReservation($membership, $late);

        $payment = Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Settled->value]);

        $this->assertSame(ReservationStatus::Confirmed, $rEarly->refresh()->status);
        $this->assertSame(ReservationStatus::Waitlist, $rLate->refresh()->status);
    }

    public function test_summary_message_reports_confirmed_and_waitlisted_counts(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 1]);
        [$open, $full] = $this->occurrences($group, ['2026-09-07', '2026-09-14']);
        Reservation::factory()->create(['class_schedule_id' => $full->id, 'status' => ReservationStatus::Confirmed]);

        $membership = Membership::factory()->create(['start_date' => '2026-09-01']);
        $this->pendingReservation($membership, $open);
        $this->pendingReservation($membership, $full);

        $payment = Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Settled->value]);

        $flash = session('success');
        $this->assertStringContainsString('Potwierdzone rezerwacje: 1', $flash);
        $this->assertStringContainsString('Na liście oczekujących: 1', $flash);
    }

    public function test_reverting_to_pending_does_not_touch_reservations(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 20]);
        [$occurrence] = $this->occurrences($group, ['2026-09-07']);

        $membership = Membership::factory()->create(['start_date' => '2026-09-01']);
        $reservation = $this->pendingReservation($membership, $occurrence);

        $payment = Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'status' => PaymentStatus::Pending,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Settled->value]);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);

        $this->actingAs($admin)->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Pending->value]);
        // Prompt 13 celowo nie cofa potwierdzeń — to osobny krok
        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_registering_an_already_settled_payment_confirms_reservations(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 20]);
        [$occurrence] = $this->occurrences($group, ['2026-09-07']);

        $client = Client::factory()->create();
        $membership = Membership::factory()->create([
            'client_id' => $client->id,
            'start_date' => '2026-09-01',
        ]);
        $reservation = $this->pendingReservation($membership, $occurrence);

        $this->actingAs($this->admin())
            ->post(route('admin.memberships.payments.store', $membership), [
                'amount' => 160,
                'mark_settled' => true,
            ])
            ->assertRedirect();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }
}

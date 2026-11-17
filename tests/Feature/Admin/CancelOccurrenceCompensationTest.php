<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Mail\ClassCancelledMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CancelOccurrenceCompensationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        Mail::fake();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function client(string $email, bool $activated = true): Client
    {
        $client = Client::factory()->create(['invitation_used_at' => $activated ? now() : null]);
        $client->user->update(['email' => $email]);
        $client->user->assignRole('client');

        return $client->fresh();
    }

    private function occurrence(string $date): ClassSchedule
    {
        $group = ClassGroup::factory()->forMonth(CarbonImmutable::parse($date)->startOfMonth())->create(['start_time' => '19:00']);

        return ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => $date, 'start_time' => '19:00']);
    }

    private function book(Client $client, ClassSchedule $occurrence, ReservationKind $kind, ReservationStatus $status = ReservationStatus::Confirmed, array $extra = []): Reservation
    {
        $membership = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-11-01']);

        return Reservation::factory()->create([
            'client_id' => $client->id,
            'membership_id' => $membership->id,
            'class_schedule_id' => $occurrence->id,
            'status' => $status,
            'kind' => $kind,
        ] + $extra);
    }

    public function test_cancelling_gives_fixed_clients_a_makeup_returns_makeups_and_drops_waitlist(): void
    {
        $occurrence = $this->occurrence('2026-11-23');

        $fixed = $this->client('stala@przyklad.pl');
        $fixedRes = $this->book($fixed, $occurrence, ReservationKind::Fixed);

        $makeup = $this->client('odrabia@przyklad.pl');
        $source = $this->book($makeup, $this->occurrence('2026-11-05'), ReservationKind::Fixed, ReservationStatus::Released);
        $credit = MakeupCredit::create(['client_id' => $makeup->id, 'source_reservation_id' => $source->id, 'used' => true]);
        $makeupRes = $this->book($makeup, $occurrence, ReservationKind::Makeup, extra: ['makeup_credit_id' => $credit->id]);

        $waiting = $this->client('czeka@przyklad.pl');
        $waitRes = $this->book($waiting, $occurrence, ReservationKind::Makeup, ReservationStatus::Waitlist);

        $optedOut = $this->client('bezmaili@przyklad.pl');
        $optedOut->update(['mail_preferences' => ['class_cancelled' => false]]);
        $this->book($optedOut, $occurrence, ReservationKind::Fixed);

        $inactive = $this->client('nieaktywna@przyklad.pl', activated: false);
        $this->book($inactive, $occurrence, ReservationKind::Fixed);

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.occurrences.cancel', $occurrence), ['reason' => 'choroba'])
            ->assertSessionHas('success');

        // zapis stały: zostaje, dostaje odrobienie ważne w tym miesiącu
        $this->assertSame(ReservationStatus::Confirmed, $fixedRes->refresh()->status);
        $this->assertSame(1, $fixed->makeupCredits()->available()->count());

        // odrabianie: miejsce zwolnione, odrobienie wraca
        $this->assertSame(ReservationStatus::Released, $makeupRes->refresh()->status);
        $this->assertFalse($credit->refresh()->used);

        // lista oczekujących zdjęta
        $this->assertSame(ReservationStatus::Released, $waitRes->refresh()->status);

        // maile: tylko aktywne konta, które nie wyłączyły powiadomień; bez listy oczekujących
        Mail::assertSent(ClassCancelledMail::class, 2);
        Mail::assertSent(ClassCancelledMail::class, fn ($m) => $m->hasTo('stala@przyklad.pl'));
        Mail::assertSent(ClassCancelledMail::class, fn ($m) => $m->hasTo('odrabia@przyklad.pl'));

        // drugie wywołanie (np. ponowne odwołanie) nie dubluje odrobień
        app(\App\Domain\Scheduling\Actions\CancelClassOccurrence::class)->compensate($occurrence->fresh());
        $this->assertSame(1, $fixed->makeupCredits()->count());

        // klientka widzi odwołane zajęcia bez przycisku „Zwalniam”
        $this->actingAs($fixed->user)
            ->get(route('client.classes.index', ['month' => '2026-11']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('membership.reservations.0.class_cancelled', true)
                ->where('membership.reservations.0.cancellable', false)
                ->where('membership.reservations.0.release_note', 'odwołane przez studio (choroba) — do odrobienia w tym miesiącu'));
    }

    public function test_restoring_removes_unused_makeups_but_keeps_used_ones(): void
    {
        $occurrence = $this->occurrence('2026-11-23');
        $a = $this->client('a@przyklad.pl');
        $b = $this->client('b@przyklad.pl');
        $resA = $this->book($a, $occurrence, ReservationKind::Fixed);
        $this->book($b, $occurrence, ReservationKind::Fixed);

        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.schedule.occurrences.cancel', $occurrence), ['reason' => 'x']);
        $b->makeupCredits()->update(['used' => true]); // B już odrobiła

        $this->actingAs($admin)->patch(route('admin.schedule.occurrences.restore', $occurrence));

        $this->assertSame(0, $a->makeupCredits()->count());
        $this->assertSame(1, $b->makeupCredits()->count());
        $this->assertSame(ReservationStatus::Confirmed, $resA->refresh()->status);
    }

    public function test_client_can_switch_off_mail_types(): void
    {
        $client = $this->client('k@przyklad.pl');

        $this->actingAs($client->user)
            ->get(route('client.mail-preferences.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/MailPreferences')
                ->where('preferences.class_cancelled', true)
                ->where('preferences.enrollment_opened', true));

        $prefs = array_fill_keys(array_keys(Client::MAIL_PREFERENCES), true);
        $prefs['enrollment_opened'] = false;

        $this->actingAs($client->user)
            ->patch(route('client.mail-preferences.update'), ['preferences' => $prefs])
            ->assertSessionHas('success');

        $client->refresh();
        $this->assertFalse($client->wantsMail('enrollment_opened'));
        $this->assertTrue($client->wantsMail('class_cancelled'));
    }
}

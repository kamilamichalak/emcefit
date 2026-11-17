<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Odrabianie i zapisy z karnetu otwartego na wolne miejsca (ustalenia z 17.11.2026).
 * „Dziś” = wtorek 10.11.2026, 10:00.
 */
class FreeSpotsTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        $this->month = CarbonImmutable::parse('2026-11-01');
    }

    private function client(): Client
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client;
    }

    private function occurrence(string $date, string $time = '19:00', int $capacity = 20): ClassSchedule
    {
        $group = ClassGroup::factory()->forMonth(CarbonImmutable::parse($date)->startOfMonth())
            ->create(['start_time' => $time, 'capacity' => $capacity]);

        return ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => $date, 'start_time' => $time]);
    }

    private function closedMembership(Client $client): Membership
    {
        return Membership::factory()->create([
            'client_id' => $client->id,
            'membership_type_id' => MembershipType::factory()->create()->id,
            'start_date' => $this->month->toDateString(),
            'end_date' => $this->month->endOfMonth()->toDateString(),
        ]);
    }

    private function openMembership(Client $client, int $entries = 4): Membership
    {
        return Membership::factory()->create([
            'client_id' => $client->id,
            'membership_type_id' => MembershipType::factory()->open($entries)->create()->id,
            'start_date' => $this->month->toDateString(),
            'end_date' => null,
        ]);
    }

    private function fixedReservation(Client $client, Membership $membership, ClassSchedule $occurrence): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => $client->id,
            'membership_id' => $membership->id,
            'class_schedule_id' => $occurrence->id,
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    private function fill(ClassSchedule $occurrence, int $count, ReservationStatus $status = ReservationStatus::Confirmed): void
    {
        foreach (range(1, $count) as $i) {
            Reservation::factory()->create(['class_schedule_id' => $occurrence->id, 'status' => $status]);
        }
    }

    private function page(User $user): AssertableInertia
    {
        $assert = null;
        $this->actingAs($user)->get(route('client.free-spots.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$assert) {
                $assert = $page->component('Client/FreeSpots');
            });

        return $assert;
    }

    public function test_released_class_gives_credit_and_client_can_book_any_free_class(): void
    {
        $client = $this->client();
        $membership = $this->closedMembership($client);
        $own = $this->fixedReservation($client, $membership, $this->occurrence('2026-11-11'));
        $other = $this->occurrence('2026-11-12', '18:00'); // inne zajęcia, inny rodzaj

        // zwalnia swoje zajęcia ponad godzinę przed → odrobienie
        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $own))->assertSessionHas('success');
        $this->assertSame(1, $client->makeupCredits()->available()->count());

        $this->actingAs($client->user)->get(route('client.free-spots.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('makeupCredits', 1)
                ->where('days.0.items.0.id', $own->class_schedule_id) // własne zwolnione też można odrobić
                ->where('days.1.items.0.id', $other->id)
                ->where('days.1.items.0.kind', 'odrobienie'));

        $this->actingAs($client->user)->post(route('client.free-spots.store', $other))
            ->assertRedirect(route('client.free-spots.index'))
            ->assertSessionHas('success');

        $booked = Reservation::where('client_id', $client->id)->where('class_schedule_id', $other->id)->sole();
        $this->assertSame(ReservationStatus::Confirmed, $booked->status);
        $this->assertSame(ReservationKind::Makeup, $booked->kind);
        $this->assertSame($membership->id, $booked->membership_id);
        $this->assertTrue(MakeupCredit::find($booked->makeup_credit_id)->used);
        $this->assertSame(0, $client->makeupCredits()->available()->count());
    }

    public function test_full_cancelled_started_and_next_month_classes_are_not_offered_for_makeup(): void
    {
        $client = $this->client();
        $membership = $this->closedMembership($client);
        MakeupCredit::factory()->for($client)->create([
            'source_reservation_id' => Reservation::factory()->released()->create([
                'client_id' => $client->id,
                'membership_id' => $membership->id,
                'class_schedule_id' => $this->occurrence('2026-11-03')->id, // minione
            ])->id,
        ]);

        $full = $this->occurrence('2026-11-12', '18:00', capacity: 3);
        $this->fill($full, 2);
        $this->fill($full, 1, ReservationStatus::PendingPayment); // czekający na płatność też zajmują miejsce

        $cancelled = $this->occurrence('2026-11-13');
        $cancelled->update(['status' => ClassOccurrenceStatus::Cancelled]);

        $this->occurrence('2026-11-10', '09:00');   // już się odbyły
        $this->occurrence('2026-12-02');            // kolejny miesiąc — odrobienie tylko w bieżącym
        $mine = $this->occurrence('2026-11-17');
        $this->fixedReservation($client, $membership, $mine); // już zapisana

        $ok = $this->occurrence('2026-11-10', '10:30', capacity: 3); // dziś, jeszcze przed startem
        $this->fill($ok, 2);

        $this->actingAs($client->user)->get(route('client.free-spots.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('days', 1)
                ->has('days.0.items', 1)
                ->where('days.0.items.0.id', $ok->id)
                ->where('days.0.items.0.free', 1));

        $this->actingAs($client->user)->post(route('client.free-spots.store', $full))
            ->assertSessionHas('error', 'Brak wolnych miejsc — ktoś był szybszy. Wybierz inne zajęcia.');
        $this->actingAs($client->user)->post(route('client.free-spots.store', $cancelled))
            ->assertSessionHas('error', 'Te zajęcia zostały odwołane.');
        $this->actingAs($client->user)->post(route('client.free-spots.store', $mine))
            ->assertSessionHas('error', 'Jesteś już zapisana na te zajęcia.');
    }

    public function test_without_credits_or_open_membership_nothing_can_be_booked(): void
    {
        $client = $this->client();
        $occurrence = $this->occurrence('2026-11-12');

        $this->actingAs($client->user)->get(route('client.free-spots.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('makeupCredits', 0)
                ->where('open', null)
                ->has('days', 0));

        $this->actingAs($client->user)->post(route('client.free-spots.store', $occurrence))
            ->assertSessionHas('error');
        $this->assertSame(0, Reservation::where('client_id', $client->id)->count());
    }

    public function test_releasing_a_makeup_class_forfeits_the_makeup(): void
    {
        $client = $this->client();
        $membership = $this->closedMembership($client);
        $own = $this->fixedReservation($client, $membership, $this->occurrence('2026-11-11'));
        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $own));

        $other = $this->occurrence('2026-11-12');
        $this->actingAs($client->user)->post(route('client.free-spots.store', $other));
        $makeup = Reservation::where('class_schedule_id', $other->id)->sole();

        // bez potwierdzenia — ostrzeżenie, nic się nie zmienia
        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $makeup))
            ->assertSessionHasErrors(['reservation' => 'To zajęcia odrabiane — po zwolnieniu miejsca odrobienie przepada.']);
        $this->assertSame(ReservationStatus::Confirmed, $makeup->fresh()->status);

        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $makeup), ['acknowledge_late' => true])
            ->assertSessionHas('success', 'Miejsce zwolnione. Odrobienie zostało wykorzystane.');

        $this->assertSame(ReservationStatus::Released, $makeup->fresh()->status);
        $this->assertSame(0, $client->makeupCredits()->available()->count());
    }

    public function test_open_membership_books_until_entries_run_out_even_before_payment(): void
    {
        $client = $this->client();
        $this->openMembership($client, entries: 2); // bez żadnej płatności

        $a = $this->occurrence('2026-11-11');
        $b = $this->occurrence('2026-11-12');
        $c = $this->occurrence('2026-11-13');

        $this->actingAs($client->user)->get(route('client.free-spots.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('open.remaining', 2)
                ->where('days.0.items.0.kind', 'otwarty'));

        $this->actingAs($client->user)->post(route('client.free-spots.store', $a))->assertSessionHas('success');
        $this->actingAs($client->user)->post(route('client.free-spots.store', $b))->assertSessionHas('success');
        $this->actingAs($client->user)->post(route('client.free-spots.store', $c))
            ->assertSessionHas('error', 'Nie masz już zajęć do odrobienia ani wejść do wykorzystania na ten termin.');

        $this->assertSame(2, Reservation::where('client_id', $client->id)->where('kind', ReservationKind::OpenEntry)->count());
    }

    public function test_open_membership_is_valid_five_weeks_from_first_entry(): void
    {
        $client = $this->client();
        $this->openMembership($client, entries: 8);

        $first = $this->occurrence('2026-11-12');
        $inside = $this->occurrence('2026-12-16');  // 12.11 + 5 tyg. - 1 dzień = 16.12
        $outside = $this->occurrence('2026-12-17');

        $this->actingAs($client->user)->post(route('client.free-spots.store', $first))->assertSessionHas('success');

        $this->actingAs($client->user)->get(route('client.free-spots.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('open.valid_to', '16 grudnia')
                ->where('days.0.items.0.id', $inside->id)
                ->has('days', 1));

        $this->actingAs($client->user)->post(route('client.free-spots.store', $outside))->assertSessionHas('error');
        $this->actingAs($client->user)->post(route('client.free-spots.store', $inside))->assertSessionHas('success');
    }

    public function test_open_entry_returns_when_released_early_and_is_lost_when_late(): void
    {
        $client = $this->client();
        $this->openMembership($client, entries: 2);

        $early = $this->occurrence('2026-11-12');
        $late = $this->occurrence('2026-11-10', '10:30'); // za 30 minut

        $this->actingAs($client->user)->post(route('client.free-spots.store', $early));
        $this->actingAs($client->user)->post(route('client.free-spots.store', $late));

        $earlyRes = Reservation::where('class_schedule_id', $early->id)->sole();
        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $earlyRes))
            ->assertSessionHas('success', 'Miejsce zwolnione, wejście wróciło na Twój karnet.');

        $lateRes = Reservation::where('class_schedule_id', $late->id)->sole();
        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $lateRes))
            ->assertSessionHasErrors('reservation');
        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $lateRes), ['acknowledge_late' => true])
            ->assertSessionHas('success', 'Miejsce zwolnione. Wejście przepadło — do startu zostało mniej niż godzina.');

        $this->assertTrue($lateRes->fresh()->late_release);
        $this->assertSame(0, $client->makeupCredits()->count()); // karnet otwarty nie daje odrobień

        $this->actingAs($client->user)->get(route('client.free-spots.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('open.remaining', 1));
    }

    public function test_client_can_take_back_a_spot_she_released_earlier(): void
    {
        $client = $this->client();
        $membership = $this->closedMembership($client);
        $occurrence = $this->occurrence('2026-11-11');
        $own = $this->fixedReservation($client, $membership, $occurrence);

        $this->actingAs($client->user)->patch(route('client.reservations.cancel', $own));
        $this->actingAs($client->user)->post(route('client.free-spots.store', $occurrence))->assertSessionHas('success');

        $row = Reservation::where('client_id', $client->id)->where('class_schedule_id', $occurrence->id)->sole();
        $this->assertSame(ReservationStatus::Confirmed, $row->status);
        $this->assertSame(ReservationKind::Makeup, $row->kind);
    }

    public function test_dashboard_links_to_free_spots_and_admin_schedule_marks_the_kind(): void
    {
        $client = $this->client();
        $this->openMembership($client, entries: 4);
        $occurrence = $this->occurrence('2026-11-12');

        $this->actingAs($client->user)->get(route('client.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('openRemaining', 4));

        $this->actingAs($client->user)->post(route('client.free-spots.store', $occurrence));

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.schedule.index', ['month' => '2026-11']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('occurrences.0.reservations.0.kind_label', 'karnet otwarty'));
    }

    public function test_guest_and_admin_cannot_use_free_spots(): void
    {
        $this->get(route('client.free-spots.index'))->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('client.free-spots.index'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CancelReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function client(): User
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client->user;
    }

    private function reservation(User $user, string $time, ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        $group = ClassGroup::factory()->create(['start_time' => $time]);
        $occurrence = ClassSchedule::factory()->create([
            'class_group_id' => $group->id,
            'date' => '2026-06-15',
            'start_time' => $time,
        ]);
        $membership = Membership::factory()->create([
            'client_id' => $user->client->id,
            'start_date' => '2026-06-01',
        ]);

        return Reservation::factory()->create([
            'client_id' => $user->client->id,
            'membership_id' => $membership->id,
            'class_schedule_id' => $occurrence->id,
            'status' => $status,
            'confirmed_at' => CarbonImmutable::parse('2026-06-10'),
        ]);
    }

    public function test_guest_and_other_clients_cannot_cancel(): void
    {
        $owner = $this->client();
        $reservation = $this->reservation($owner, '18:00');

        $this->patch(route('client.reservations.cancel', $reservation))->assertRedirect(route('login'));

        $this->actingAs($this->client())
            ->patch(route('client.reservations.cancel', $reservation))
            ->assertForbidden();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_cancelling_more_than_an_hour_before_grants_a_makeup_credit(): void
    {
        $user = $this->client();
        $reservation = $this->reservation($user, '18:00'); // 6 h do startu

        $this->actingAs($user)
            ->patch(route('client.reservations.cancel', $reservation))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'prawo do odrobienia'));

        $this->assertSame(ReservationStatus::Released, $reservation->refresh()->status);
        $this->assertDatabaseHas('makeup_credits', [
            'source_reservation_id' => $reservation->id,
            'client_id' => $user->client->id,
            'expires_end_of_month' => true,
            'used' => false,
        ]);
    }

    public function test_late_cancellation_without_acknowledgement_is_rejected(): void
    {
        $user = $this->client();
        $reservation = $this->reservation($user, '12:30'); // 30 min do startu

        $this->actingAs($user)
            ->patch(route('client.reservations.cancel', $reservation))
            ->assertSessionHasErrors('reservation');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
        $this->assertDatabaseCount('makeup_credits', 0);
    }

    public function test_late_cancellation_with_acknowledgement_drops_the_seat_without_a_credit(): void
    {
        $user = $this->client();
        $reservation = $this->reservation($user, '12:30');

        $this->actingAs($user)
            ->patch(route('client.reservations.cancel', $reservation), ['acknowledge_late' => true])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Bez prawa do odrobienia'));

        $this->assertSame(ReservationStatus::Released, $reservation->refresh()->status);
        $this->assertDatabaseCount('makeup_credits', 0);
    }

    public function test_exactly_one_hour_before_still_grants_a_credit(): void
    {
        $user = $this->client();
        $reservation = $this->reservation($user, '13:00'); // dokładnie 60 min

        $this->actingAs($user)->patch(route('client.reservations.cancel', $reservation));

        $this->assertDatabaseCount('makeup_credits', 1);
    }

    public function test_past_class_cannot_be_cancelled(): void
    {
        $user = $this->client();
        $reservation = $this->reservation($user, '09:00'); // już się odbyły

        $this->actingAs($user)
            ->patch(route('client.reservations.cancel', $reservation))
            ->assertSessionHas('error');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_non_confirmed_reservation_cannot_be_cancelled_here(): void
    {
        $user = $this->client();
        $reservation = $this->reservation($user, '18:00', ReservationStatus::PendingPayment);

        $this->actingAs($user)
            ->patch(route('client.reservations.cancel', $reservation))
            ->assertSessionHas('error');

        $this->assertSame(ReservationStatus::PendingPayment, $reservation->refresh()->status);
        $this->assertDatabaseCount('makeup_credits', 0);
    }

    public function test_my_classes_page_marks_only_future_confirmed_reservations_as_cancellable(): void
    {
        $user = $this->client();
        $membership = Membership::factory()->create([
            'client_id' => $user->client->id,
            'start_date' => '2026-06-01',
        ]);

        $make = function (string $time, ReservationStatus $status) use ($user, $membership): void {
            $group = ClassGroup::factory()->create(['start_time' => $time]);
            $occurrence = ClassSchedule::factory()->create([
                'class_group_id' => $group->id,
                'date' => '2026-06-15',
                'start_time' => $time,
            ]);
            Reservation::factory()->create([
                'client_id' => $user->client->id,
                'membership_id' => $membership->id,
                'class_schedule_id' => $occurrence->id,
                'status' => $status,
            ]);
        };

        $make('18:00', ReservationStatus::Confirmed);       // przyszłość + potwierdzona -> tak
        $make('09:00', ReservationStatus::Confirmed);       // przeszłość -> nie
        $make('19:00', ReservationStatus::PendingPayment);  // nie potwierdzona -> nie

        $this->actingAs($user)
            ->get(route('client.classes.index', ['month' => '2026-06']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('membership.reservations', function ($rows) {
                    $rows = collect($rows);

                    return $rows->pluck('cancellable')->sort()->values()->all() === [false, false, true]
                        // 09:00 minęło, 18:00 i 19:00 jeszcze nie (Prompt 12a)
                        && $rows->pluck('is_past')->sort()->values()->all() === [false, false, true];
                }));
    }
}

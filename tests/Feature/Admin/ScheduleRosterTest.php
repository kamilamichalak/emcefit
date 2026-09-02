<?php

namespace Tests\Feature\Admin;

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

class ScheduleRosterTest extends TestCase
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

    private function reservationFor(string $name, ClassSchedule $occurrence, ReservationStatus $status, ?string $confirmedAt): Reservation
    {
        $client = Client::factory()->create();
        $client->user->update(['name' => $name]);

        return Reservation::factory()->create([
            'client_id' => $client->id,
            'membership_id' => Membership::factory()->create(['client_id' => $client->id])->id,
            'class_schedule_id' => $occurrence->id,
            'status' => $status,
            'confirmed_at' => $confirmedAt ? CarbonImmutable::parse($confirmedAt) : null,
        ]);
    }

    public function test_schedule_occurrence_carries_a_sorted_roster(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 1, 'start_time' => '18:00']);
        $occurrence = ClassSchedule::factory()->create([
            'class_group_id' => $group->id,
            'date' => '2026-09-07',
            'start_time' => '18:00',
        ]);

        $this->reservationFor('Cecylia Waitlist', $occurrence, ReservationStatus::Waitlist, '2026-09-05');
        $this->reservationFor('Bartek Pending', $occurrence, ReservationStatus::PendingPayment, null);
        $this->reservationFor('Anna Confirmed', $occurrence, ReservationStatus::Confirmed, '2026-09-03');

        $this->actingAs($this->admin())
            ->get(route('admin.schedule.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Schedule/Index')
                ->has('occurrences.0.type_icon')
                ->where('occurrences.0.confirmed_count', 1)
                ->where('occurrences.0.waitlist_count', 1)
                ->has('occurrences.0.reservations', 3)
                ->where('occurrences.0.reservations.0.client_name', 'Anna Confirmed')
                ->where('occurrences.0.reservations.0.status', 'potwierdzona')
                ->where('occurrences.0.reservations.1.client_name', 'Cecylia Waitlist')
                ->where('occurrences.0.reservations.1.status', 'waitlist')
                ->where('occurrences.0.reservations.2.client_name', 'Bartek Pending')
                ->where('occurrences.0.reservations.2.status', 'oczekuje_platnosci'));
    }

    public function test_waitlisted_clients_are_ordered_by_confirmation_date(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 0]);
        $occurrence = ClassSchedule::factory()->create([
            'class_group_id' => $group->id,
            'date' => '2026-09-07',
        ]);

        $this->reservationFor('Later Payer', $occurrence, ReservationStatus::Waitlist, '2026-09-10');
        $this->reservationFor('Earlier Payer', $occurrence, ReservationStatus::Waitlist, '2026-09-04');

        $this->actingAs($this->admin())
            ->get(route('admin.schedule.index', ['month' => '2026-09']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('occurrences.0.reservations.0.client_name', 'Earlier Payer')
                ->where('occurrences.0.reservations.1.client_name', 'Later Payer'));
    }
}

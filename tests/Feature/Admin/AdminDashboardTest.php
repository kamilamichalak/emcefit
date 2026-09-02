<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        CarbonImmutable::setTestNow('2026-09-10 09:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function upcoming(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-01');
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_pending_payments_are_sorted_by_how_long_they_wait(): void
    {
        Payment::factory()->create(['amount' => 100, 'reported_date' => '2026-09-01']); // 9 dni
        Payment::factory()->create(['amount' => 50, 'reported_date' => '2026-09-08']);  // 2 dni
        Payment::factory()->settled()->create(['amount' => 200]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->has('pendingPayments', 2)
                ->where('pendingPaymentsTotal', 150)
                ->where('pendingPayments.0.days_waiting', 9)
                ->where('pendingPayments.1.days_waiting', 2));
    }

    public function test_unpaid_reservations_within_24h_are_flagged(): void
    {
        $group = ClassGroup::factory()->create(['start_time' => '18:00']);
        $soon = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-10', 'start_time' => '18:00']);
        $later = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-20', 'start_time' => '18:00']);

        Reservation::factory()->create(['class_schedule_id' => $soon->id, 'status' => ReservationStatus::PendingPayment]);
        Reservation::factory()->create(['class_schedule_id' => $later->id, 'status' => ReservationStatus::PendingPayment]);
        Reservation::factory()->create(['class_schedule_id' => $soon->id, 'status' => ReservationStatus::Confirmed]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('unpaidSoon', 1)
                ->where('unpaidSoon.0.hours_left', 9)
                ->has('unpaidSoon.0.type_icon'));
    }

    public function test_upcoming_enrollment_state_is_reported(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('enrollmentUpcoming.value', '2026-10')
                ->where('enrollmentUpcoming.open', false)
                ->has('clientsNotEnrolled', 0));

        EnrollmentWindow::factory()->forMonth($this->upcoming())->open()->create();

        $active = Client::factory()->create(['status' => ClientStatus::Active]);
        $enrolled = Client::factory()->create(['status' => ClientStatus::Active]);
        Membership::factory()->create(['client_id' => $enrolled->id, 'start_date' => '2026-10-01']);
        Client::factory()->create(['status' => ClientStatus::Inactive]); // nieaktywny — pomijany

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('enrollmentUpcoming.open', true)
                ->has('clientsNotEnrolled', 1)
                ->where('clientsNotEnrolled.0.id', $active->id));
    }

    public function test_admin_landing_on_generic_dashboard_is_redirected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }
}

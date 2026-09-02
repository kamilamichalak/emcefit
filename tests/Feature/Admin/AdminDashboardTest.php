<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\MakeupCredit;
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

    public function test_low_occupancy_lists_underfilled_upcoming_classes(): void
    {
        $group = ClassGroup::factory()->create(['capacity' => 10, 'start_time' => '18:00']);
        $quiet = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-12', 'start_time' => '18:00']);
        $full = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-13', 'start_time' => '18:00']);
        $farOff = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-30', 'start_time' => '18:00']);

        Reservation::factory()->count(2)->create(['class_schedule_id' => $quiet->id, 'status' => ReservationStatus::Confirmed]); // 2/10 = 20%
        Reservation::factory()->count(6)->create(['class_schedule_id' => $full->id, 'status' => ReservationStatus::Confirmed]); // 6/10 = 60%
        Reservation::factory()->create(['class_schedule_id' => $farOff->id, 'status' => ReservationStatus::Confirmed]); // poza oknem 7 dni

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('lowOccupancy', 1)
                ->where('lowOccupancy.0.id', $quiet->id)
                ->where('lowOccupancy.0.confirmed', 2)
                ->where('lowOccupancy.0.capacity', 10));
    }

    public function test_waitlist_is_summed_and_split_by_occurrence(): void
    {
        $group = ClassGroup::factory()->create();
        $a = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-15']);
        $b = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-16']);
        $past = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => '2026-09-01']);

        Reservation::factory()->count(2)->create(['class_schedule_id' => $a->id, 'status' => ReservationStatus::Waitlist]);
        Reservation::factory()->create(['class_schedule_id' => $b->id, 'status' => ReservationStatus::Waitlist]);
        Reservation::factory()->create(['class_schedule_id' => $past->id, 'status' => ReservationStatus::Waitlist]); // przeszłość — pomijana

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('waitlistTotal', 3)
                ->has('waitlist', 2)
                ->where('waitlist.0.count', 2));
    }

    public function test_clients_without_login_are_listed(): void
    {
        $noLogin = Client::factory()->create(['invitation_used_at' => null]);
        Client::factory()->create(['invitation_used_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('clientsWithoutLogin', 1)
                ->where('clientsWithoutLogin.0.id', $noLogin->id));
    }

    public function test_makeup_credits_expiring_soon_show_only_near_month_end(): void
    {
        $client = Client::factory()->create();
        MakeupCredit::factory()->count(2)->for($client)->create(['expires_end_of_month' => true, 'used' => false]);

        // 10 września — do końca miesiąca 20 dni, nic nie pokazujemy
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('makeupExpiring', 0));

        CarbonImmutable::setTestNow('2026-09-26 09:00:00'); // 4 dni do końca miesiąca

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('makeupExpiring', 1)
                ->where('makeupExpiring.0.client_id', $client->id)
                ->where('makeupExpiring.0.count', 2));
    }

    public function test_admin_landing_on_generic_dashboard_is_redirected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }
}

<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Enums\PaymentStatus;
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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function client(): User
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client->user;
    }

    public function test_counter_is_zero_when_no_credits(): void
    {
        $this->actingAs($this->client())
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Dashboard')
                ->where('makeupCredits', 0));
    }

    public function test_counter_sums_only_available_credits_of_this_client(): void
    {
        $user = $this->client();
        $client = $user->client;

        // liczą się: niewykorzystane, świeże (wygasają z końcem tego miesiąca)
        MakeupCredit::factory()->count(2)->for($client)->create();
        // bezterminowe też się liczą
        MakeupCredit::factory()->for($client)->create(['expires_end_of_month' => false]);
        // wykorzystane — nie
        MakeupCredit::factory()->for($client)->used()->create();
        // wygasłe (świeże = false, utworzone w zeszłym miesiącu) — nie
        MakeupCredit::factory()->for($client)->create([
            'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
        ]);
        // cudzy kredyt — nie
        MakeupCredit::factory()->create();

        $this->actingAs($user)
            ->get(route('client.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('makeupCredits', 3));
    }

    public function test_dashboard_shows_upcoming_classes_membership_and_next_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00'));
        $user = $this->client();
        $user->update(['name' => 'Anna Kowalska']);
        $client = $user->client;
        $month = CarbonImmutable::parse('2026-11-01');

        $type = MembershipType::factory()->create(['name' => 'Zamknięty 1x/tydzień']);
        $group = ClassGroup::factory()->forMonth($month)->create(['weekday' => 3, 'start_time' => '19:00', 'duration_minutes' => 55]);
        $membership = Membership::factory()->create([
            'client_id' => $client->id,
            'membership_type_id' => $type->id,
            'start_date' => $month->toDateString(),
            'end_date' => $month->endOfMonth()->toDateString(),
        ]);
        Payment::factory()->create(['membership_id' => $membership->id, 'client_id' => $client->id, 'status' => PaymentStatus::Settled]);

        // minione, zwolnione i 4 przyszłe — pokazujemy 3 najbliższe przyszłe
        foreach (['2026-11-04' => ReservationStatus::Confirmed, '2026-11-11' => ReservationStatus::Released,
            '2026-11-18' => ReservationStatus::Confirmed, '2026-11-25' => ReservationStatus::Confirmed,
            '2026-11-12' => ReservationStatus::Waitlist, '2026-11-26' => ReservationStatus::Confirmed] as $date => $status) {
            $occurrence = ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => $date, 'start_time' => '19:00']);
            Reservation::factory()->create([
                'membership_id' => $membership->id,
                'client_id' => $client->id,
                'class_schedule_id' => $occurrence->id,
                'status' => $status,
            ]);
        }

        EnrollmentWindow::factory()->create(['year' => 2026, 'month' => 12, 'open' => true]);

        $this->actingAs($user)
            ->get(route('client.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Dashboard')
                ->where('firstName', 'Anna')
                ->has('upcoming', 3)
                ->where('upcoming.0.status', 'waitlist')
                ->where('upcoming.0.cancellable', false)
                ->where('upcoming.1.end_time', '19:55')
                ->where('upcoming.1.cancellable', true)
                ->where('upcoming.1.kind', 'stala')
                ->where('upcoming.1.starts_at', fn (string $v) => str_starts_with($v, '2026-11-18T19:00:00'))
                ->where('current.type_name', 'Zamknięty 1x/tydzień')
                ->where('current.paid', true)
                ->where('next.value', '2026-12')
                ->where('next.enrollment_open', true)
                ->where('next.membership', null));
    }

    public function test_dashboard_without_membership_and_closed_enrollment(): void
    {
        $this->actingAs($this->client())
            ->get(route('client.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('upcoming', 0)
                ->where('current', null)
                ->where('next.enrollment_open', false));
    }
}

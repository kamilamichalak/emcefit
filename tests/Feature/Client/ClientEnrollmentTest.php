<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ClassTypeSeeder;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ClassTypeSeeder::class);
        $this->seed(MembershipTypeSeeder::class);
    }

    private function client(): User
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client->user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('client.enrollment.create'))->assertRedirect(route('login'));
    }

    public function test_non_client_cannot_open_enrollment(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('client.enrollment.create'))->assertForbidden();
    }

    public function test_page_lists_this_month_groups_and_monthly_closed_pricing(): void
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        ClassGroup::factory()->forMonth($thisMonth)->count(2)->create();
        ClassGroup::factory()->forMonth($nextMonth)->create(); // nie ten miesiąc

        $this->actingAs($this->client())
            ->get(route('client.enrollment.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Enroll')
                ->where('month.value', $thisMonth->format('Y-m'))
                ->has('classGroups', 2)
                ->has('classGroups.0.free_spots')
                // z seeda: 1x/2x/3x/4x miesięczne zamknięte
                ->has('pricing', 4)
                ->where('pricing.0.sessions_per_week', 1));
    }

    public function test_next_month_can_be_selected(): void
    {
        $nextMonth = CarbonImmutable::today()->startOfMonth()->addMonthNoOverflow();
        ClassGroup::factory()->forMonth($nextMonth)->count(3)->create();

        $this->actingAs($this->client())
            ->get(route('client.enrollment.create', ['month' => $nextMonth->format('Y-m')]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('month.value', $nextMonth->format('Y-m'))
                ->has('classGroups', 3));
    }

    public function test_out_of_range_month_falls_back_to_current(): void
    {
        $this->actingAs($this->client())
            ->get(route('client.enrollment.create', ['month' => '2099-01']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('month.value', CarbonImmutable::today()->format('Y-m')));
    }

    public function test_pricing_only_includes_calendar_month_closed_variants(): void
    {
        // z seeda jest tez "Zamknięty 2x/tydzień — 2 tygodnie" (tygodnie_od_pierwszego_wejscia) — ma być pominięty
        $this->actingAs($this->client())
            ->get(route('client.enrollment.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pricing', fn ($pricing) => collect($pricing)
                    ->pluck('sessions_per_week')->sort()->values()->all() === [1, 2, 3, 4]));

        $this->assertTrue(
            MembershipType::where('name', 'Zamknięty 2x/tydzień — 2 tygodnie')->exists(),
        );
    }
}

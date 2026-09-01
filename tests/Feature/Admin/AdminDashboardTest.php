<?php

namespace Tests\Feature\Admin;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
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
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_lists_memberships_ending_within_seven_days(): void
    {
        Membership::factory()->endingInDays(3)->create();
        Membership::factory()->endingInDays(30)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->has('endingMemberships', 1));
    }

    public function test_dashboard_lists_only_pending_payments(): void
    {
        Payment::factory()->create(['amount' => 100]);
        Payment::factory()->settled()->create(['amount' => 200]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pendingPayments', 1)
                ->where('pendingPaymentsTotal', 100));
    }

    public function test_dashboard_counts_open_memberships_active_this_month(): void
    {
        $openType = MembershipType::factory()->open()->create();

        Membership::factory()->for($openType, 'membershipType')->create([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
        // otwarty, ale poza biezacym miesiacem
        Membership::factory()->for($openType, 'membershipType')->create([
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonths(2)->toDateString(),
        ]);
        // zamkniety w tym miesiacu — nie liczony
        Membership::factory()->create([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('openMembershipsThisMonth', 1)
                ->where('openMembershipsLimit', 20));
    }

    public function test_admin_landing_on_generic_dashboard_is_redirected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }
}

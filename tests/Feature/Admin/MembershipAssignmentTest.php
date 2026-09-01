<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Models\MembershipType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipAssignmentTest extends TestCase
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

    public function test_non_admin_cannot_open_assignment_form(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.clients.memberships.create', $client))
            ->assertForbidden();
    }

    public function test_admin_assigns_closed_monthly_membership_with_computed_end_date(): void
    {
        $client = Client::factory()->create();
        $type = MembershipType::factory()->create(); // zamkniety, miesiac kalendarzowy x1

        $this->actingAs($this->admin())
            ->post(route('admin.clients.memberships.store', $client), [
                'membership_type_id' => $type->id,
                'start_date' => '2026-09-01',
                'first_entry_date' => '',
                'end_date' => '',
                'entries_remaining' => '',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $membership = $client->memberships()->sole();

        $this->assertSame('2026-09-01', $membership->start_date->toDateString());
        $this->assertSame('2026-09-30', $membership->end_date->toDateString());
        $this->assertNull($membership->entries_remaining);
    }

    public function test_admin_assigns_open_package_and_gets_default_entries(): void
    {
        $client = Client::factory()->create();
        $type = MembershipType::factory()->open(4)->create();

        $this->actingAs($this->admin())
            ->post(route('admin.clients.memberships.store', $client), [
                'membership_type_id' => $type->id,
                'start_date' => '2026-09-01',
                'first_entry_date' => '2026-09-03',
                'end_date' => '',
                'entries_remaining' => '',
            ])
            ->assertRedirect();

        $membership = $client->memberships()->sole();

        $this->assertSame(MembershipMode::Open, $membership->membershipType->mode);
        $this->assertSame(4, $membership->entries_remaining);
        // 5 tygodni od pierwszego wejscia (2026-09-03) minus dzien
        $this->assertSame('2026-10-07', $membership->end_date->toDateString());
    }

    public function test_admin_can_override_end_date_and_entries(): void
    {
        $client = Client::factory()->create();
        $type = MembershipType::factory()->open(8)->create();

        $this->actingAs($this->admin())
            ->post(route('admin.clients.memberships.store', $client), [
                'membership_type_id' => $type->id,
                'start_date' => '2026-09-01',
                'first_entry_date' => '',
                'end_date' => '2026-12-31',
                'entries_remaining' => '3',
            ])
            ->assertRedirect();

        $membership = $client->memberships()->sole();

        $this->assertSame('2026-12-31', $membership->end_date->toDateString());
        $this->assertSame(3, $membership->entries_remaining);
    }

    public function test_membership_type_must_exist(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.clients.memberships.store', $client), [
                'membership_type_id' => 9999,
            ])
            ->assertSessionHasErrors('membership_type_id');
    }
}

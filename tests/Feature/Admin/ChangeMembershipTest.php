<?php

namespace Tests\Feature\Admin;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ChangeMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(MembershipTypeSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function membership(): Membership
    {
        $type = MembershipType::where('name', 'Zamknięty 2x/tydzień — miesięczny')->sole();

        return Membership::factory()->create([
            'membership_type_id' => $type->id,
            'price_locked' => $type->price,
            'start_date' => '2026-09-01',
        ]);
    }

    public function test_non_admin_cannot_change_a_membership(): void
    {
        $membership = $this->membership();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.memberships.edit', $membership))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.memberships.update', $membership), [])
            ->assertForbidden();
    }

    public function test_edit_page_shows_membership_and_type_list(): void
    {
        $membership = $this->membership();

        $this->actingAs($this->admin())
            ->get(route('admin.memberships.edit', $membership))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Memberships/Edit')
                ->where('membership.type_id', $membership->membership_type_id)
                ->where('membership.month_label', 'wrzesień 2026')
                ->has('membershipTypes', MembershipType::count())
                ->has('membershipTypes.0.sessions_per_week'));
    }

    public function test_admin_changes_type_and_price_and_it_is_flagged(): void
    {
        $membership = $this->membership();
        $newType = MembershipType::where('name', 'Zamknięty 1x/tydzień — miesięczny')->sole();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.memberships.update', $membership), [
                'membership_type_id' => $newType->id,
                'price' => '75.00', // rabat: mniej niż cennikowe 100
                'note' => 'rabat świąteczny',
            ])
            ->assertRedirect(route('admin.clients.show', $membership->client_id))
            ->assertSessionHas('success');

        $membership->refresh();
        $this->assertSame($newType->id, $membership->membership_type_id);
        $this->assertSame('75.00', $membership->price_locked);
        $this->assertSame($admin->id, $membership->modified_by_id);
        $this->assertNotNull($membership->modified_at);
        $this->assertSame('rabat świąteczny', $membership->admin_note);
    }

    public function test_change_does_not_touch_existing_payments(): void
    {
        $membership = $this->membership();
        $payment = Payment::factory()->settled()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'amount' => '160.00',
        ]);
        $newType = MembershipType::where('name', 'Zamknięty 1x/tydzień — miesięczny')->sole();

        $this->actingAs($this->admin())
            ->patch(route('admin.memberships.update', $membership), [
                'membership_type_id' => $newType->id,
                'price' => '100.00',
            ]);

        $this->assertSame('160.00', $payment->refresh()->amount);
    }

    public function test_type_and_price_are_validated(): void
    {
        $membership = $this->membership();

        $this->actingAs($this->admin())
            ->patch(route('admin.memberships.update', $membership), [
                'membership_type_id' => 999999,
                'price' => '-5',
            ])
            ->assertSessionHasErrors(['membership_type_id', 'price']);
    }

    public function test_client_card_surfaces_the_manual_change(): void
    {
        $membership = $this->membership();
        $admin = $this->admin();
        $newType = MembershipType::where('name', 'Zamknięty 1x/tydzień — miesięczny')->sole();

        $this->actingAs($admin)->patch(route('admin.memberships.update', $membership), [
            'membership_type_id' => $newType->id,
            'price' => '80.00',
            'note' => 'stały klient',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.show', $membership->client_id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('memberships.0.month_label', 'wrzesień 2026')
                ->where('memberships.0.price_locked', '80.00')
                ->where('memberships.0.modified.by', $admin->name)
                ->where('memberships.0.modified.note', 'stały klient'));
    }
}

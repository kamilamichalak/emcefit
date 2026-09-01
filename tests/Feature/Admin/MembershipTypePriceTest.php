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

class MembershipTypePriceTest extends TestCase
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

    public function test_non_admin_cannot_view_or_update_prices(): void
    {
        $user = User::factory()->create();
        $type = MembershipType::first();

        $this->actingAs($user)->get(route('admin.membership-types.index'))->assertForbidden();
        $this->actingAs($user)
            ->patch(route('admin.membership-types.price', $type), ['price' => 999])
            ->assertForbidden();
    }

    public function test_index_lists_every_type_with_read_only_attributes(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.membership-types.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/MembershipTypes/Index')
                ->has('membershipTypes', MembershipType::count())
                ->has('membershipTypes.0', fn (AssertableInertia $row) => $row
                    ->has('id')->has('name')->has('mode')->has('sessions_per_week')
                    ->has('entry_count')->has('validity')->has('price')));
    }

    public function test_admin_updates_only_the_price(): void
    {
        $type = MembershipType::where('name', 'Zamknięty 2x/tydzień — miesięczny')->sole();
        $originalName = $type->name;
        $originalMode = $type->mode;

        $this->actingAs($this->admin())
            ->patch(route('admin.membership-types.price', $type), ['price' => '175.50'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $type->refresh();
        $this->assertSame('175.50', $type->price);
        $this->assertSame($originalName, $type->name);
        $this->assertSame($originalMode, $type->mode);
    }

    public function test_price_change_does_not_touch_existing_payments(): void
    {
        $type = MembershipType::where('sessions_per_week', 2)->where('validity_period_type', 'miesiac_kalendarzowy')->first();

        $membership = Membership::factory()->create([
            'membership_type_id' => $type->id,
        ]);
        $payment = Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $membership->client_id,
            'amount' => '160.00',
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.membership-types.price', $type), ['price' => '999.00']);

        $this->assertSame('160.00', $payment->refresh()->amount);
    }

    public function test_price_must_be_a_non_negative_number(): void
    {
        $type = MembershipType::first();

        $this->actingAs($this->admin())
            ->patch(route('admin.membership-types.price', $type), ['price' => '-5'])
            ->assertSessionHasErrors('price');

        $this->actingAs($this->admin())
            ->patch(route('admin.membership-types.price', $type), ['price' => 'abc'])
            ->assertSessionHasErrors('price');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientManagementTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.clients.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_client_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.clients.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_the_client_list(): void
    {
        Client::factory()->create(['status' => ClientStatus::Active, 'invitation_used_at' => now()]);
        Client::factory()->create(['status' => ClientStatus::Inactive, 'invitation_used_at' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Clients/Index')
                ->has('clients.data', 2)
                ->has('clients.data.0.status_label')
                ->has('clients.data.0.login_configured'));
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        Client::factory()->create();
        Client::factory()->inactive()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.clients.index', ['status' => ClientStatus::Inactive->value]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 1));
    }

    public function test_admin_creates_a_client_with_basic_data_only(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.clients.store'), [
                'name' => 'Anna Kowalska',
                'email' => 'Anna@Example.com',
                'phone' => '600 100 200',
                'birth_date' => '1990-05-10',
            ])
            ->assertSessionHas('success');

        $user = User::where('email', 'anna@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('client'));

        $client = $user->client;
        $this->assertNotNull($client);
        $this->assertSame('600 100 200', $client->phone);
        // klient startuje jako nieaktywny, bez zgod
        $this->assertSame(ClientStatus::Inactive, $client->status);
        $this->assertNull($client->terms_accepted_at);
        $this->assertNull($client->health_declaration_at);
        $this->assertNull($client->invitation_used_at);
        $this->assertNotNull($client->join_date);
    }

    public function test_store_redirects_to_the_client_card(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.clients.store'), [
                'name' => 'Jan Nowak',
                'email' => 'jan@example.com',
            ])
            ->assertRedirect(route('admin.clients.show', Client::firstOrFail()));
    }

    public function test_creating_a_client_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.clients.store'), [
                'name' => 'Jan Nowak',
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors(['email' => 'Ten adres e-mail jest już zajęty.']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_required_field_message_is_in_polish(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.clients.store'), ['name' => '', 'email' => 'a@b.pl'])
            ->assertSessionHasErrors(['name' => 'Pole imię i nazwisko jest wymagane.']);
    }

    public function test_admin_updates_client_basic_data(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.clients.update', $client), [
                'name' => 'Nowe Imię',
                'email' => 'nowy@example.com',
                'phone' => '111 222 333',
                'birth_date' => '1985-01-01',
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $client->refresh();

        $this->assertSame('Nowe Imię', $client->user->name);
        $this->assertSame('nowy@example.com', $client->user->email);
        $this->assertSame('111 222 333', $client->phone);
        $this->assertSame('1985-01-01', $client->birth_date->toDateString());
    }

    public function test_admin_can_toggle_client_status(): void
    {
        $client = Client::factory()->create(['status' => ClientStatus::Active]);

        $this->actingAs($this->admin())
            ->patch(route('admin.clients.status', $client))
            ->assertRedirect();

        $this->assertSame(ClientStatus::Inactive, $client->fresh()->status);

        $this->actingAs($this->admin())
            ->patch(route('admin.clients.status', $client))
            ->assertRedirect();

        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
    }

    public function test_client_card_shows_membership_and_payment_history_with_summary(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->create([
            'client_id' => $client->id,
            'end_date' => now()->addWeek()->toDateString(),
        ]);
        Payment::factory()->settled()->create([
            'membership_id' => $membership->id,
            'client_id' => $client->id,
            'amount' => 160,
        ]);
        Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $client->id,
            'amount' => 20,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Clients/Show')
                ->has('memberships', 1)
                ->has('payments', 2)
                ->where('summary.memberships_count', 1)
                ->where('summary.settled_total', 160)
                ->where('summary.pending_total', 20)
                ->where('summary.pending_count', 1)
                ->where('summary.active_membership.type_name', $membership->membershipType->name));
    }
}

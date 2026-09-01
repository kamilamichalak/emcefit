<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
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
        Client::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Clients/Index')
                ->has('clients.data', 3));
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        Client::factory()->create();
        Client::factory()->inactive()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.clients.index', ['status' => ClientStatus::Inactive->value]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 1));
    }

    public function test_admin_can_create_a_client(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.clients.store'), [
                'name' => 'Anna Kowalska',
                'email' => 'anna@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone' => '600 100 200',
                'birth_date' => '1990-05-10',
                'status' => ClientStatus::Active->value,
                'join_date' => '',
                'terms_accepted' => true,
                'health_declaration' => true,
            ])
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'anna@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('client'));

        $client = $user->client;
        $this->assertNotNull($client);
        $this->assertSame('600 100 200', $client->phone);
        $this->assertSame(ClientStatus::Active, $client->status);
        $this->assertNotNull($client->join_date);
        $this->assertNotNull($client->terms_accepted_at);
        $this->assertNotNull($client->health_declaration_at);
    }

    public function test_creating_a_client_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.clients.store'), [
                'name' => 'Jan Nowak',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status' => ClientStatus::Active->value,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_admin_can_update_client_data_without_changing_password(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.clients.update', $client), [
                'name' => 'Nowe Imię',
                'email' => 'nowy@example.com',
                'password' => '',
                'password_confirmation' => '',
                'phone' => '111 222 333',
                'birth_date' => '1985-01-01',
                'status' => ClientStatus::Active->value,
                'join_date' => $client->join_date->toDateString(),
                'terms_accepted' => true,
                'health_declaration' => false,
            ])
            ->assertRedirect(route('admin.clients.index'));

        $client->refresh();

        $this->assertSame('Nowe Imię', $client->user->name);
        $this->assertSame('nowy@example.com', $client->user->email);
        $this->assertSame('111 222 333', $client->phone);
        $this->assertNull($client->health_declaration_at);
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
}

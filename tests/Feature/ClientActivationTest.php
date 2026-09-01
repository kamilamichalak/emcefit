<?php

namespace Tests\Feature;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function client(array $state = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'status' => ClientStatus::Inactive,
            'terms_accepted_at' => null,
            'health_declaration_at' => null,
            'invitation_used_at' => null,
        ], $state));

        // odwzorowuje produkcje — CreateClient nadaje userowi role 'client'
        $client->user->assignRole('client');

        return $client;
    }

    private function signedShowUrl(Client $client, ?\DateTimeInterface $expires = null): string
    {
        return URL::temporarySignedRoute(
            'client.activate.show',
            $expires ?? now()->addDays(7),
            ['client' => $client->id],
        );
    }

    public function test_activation_page_loads_with_a_valid_signed_link(): void
    {
        $client = $this->client();

        $this->get($this->signedShowUrl($client))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ActivateAccount')
                ->where('client.email', $client->user->email)
                ->has('regulaminHtml')
                ->has('submitUrl'));
    }

    public function test_invalid_signature_shows_error_page(): void
    {
        $client = $this->client();

        $this->get(route('client.activate.show', $client).'?expires=9999999999&signature=deadbeef')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ActivationInvalid')
                ->where('reason', 'invalid'));
    }

    public function test_expired_link_shows_error_page(): void
    {
        $client = $this->client();

        $this->get($this->signedShowUrl($client, now()->subDay()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ActivationInvalid')
                ->where('reason', 'expired'));
    }

    public function test_used_link_shows_error_page(): void
    {
        $client = $this->client(['invitation_used_at' => now()]);

        $this->get($this->signedShowUrl($client))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ActivationInvalid')
                ->where('reason', 'used'));
    }

    public function test_client_activates_account(): void
    {
        $client = $this->client();

        $this->post($this->signedShowUrl($client), [
            'password' => 'noweHaslo123',
            'password_confirmation' => 'noweHaslo123',
            'terms_accepted' => true,
            'health_declaration' => true,
        ])->assertRedirect(route('client.dashboard'));

        $client->refresh();
        $this->assertTrue(Hash::check('noweHaslo123', $client->user->password));
        $this->assertNotNull($client->terms_accepted_at);
        $this->assertNotNull($client->health_declaration_at);
        $this->assertNotNull($client->invitation_used_at);
        $this->assertSame(ClientStatus::Active, $client->status);
        $this->assertAuthenticatedAs($client->user);
    }

    public function test_both_consents_are_required(): void
    {
        $client = $this->client();

        $this->post($this->signedShowUrl($client), [
            'password' => 'noweHaslo123',
            'password_confirmation' => 'noweHaslo123',
            'terms_accepted' => true,
            'health_declaration' => false,
        ])->assertSessionHasErrors('health_declaration');

        $this->assertNull($client->fresh()->invitation_used_at);
        $this->assertGuest();
    }

    public function test_password_must_be_confirmed(): void
    {
        $client = $this->client();

        $this->post($this->signedShowUrl($client), [
            'password' => 'noweHaslo123',
            'password_confirmation' => 'inne',
            'terms_accepted' => true,
            'health_declaration' => true,
        ])->assertSessionHasErrors('password');
    }

    public function test_link_cannot_be_reused(): void
    {
        $client = $this->client();
        $url = $this->signedShowUrl($client);

        $this->post($url, [
            'password' => 'noweHaslo123',
            'password_confirmation' => 'noweHaslo123',
            'terms_accepted' => true,
            'health_declaration' => true,
        ])->assertRedirect(route('client.dashboard'));

        $this->post('/logout');

        $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ActivationInvalid')
            ->where('reason', 'used'));
    }

    public function test_admin_sees_activation_link_only_for_unactivated_client(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $pending = $this->client();
        $this->actingAs($admin)
            ->get(route('admin.clients.show', $pending))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activation.used_at', null)
                ->where('activation.link', fn ($link) => is_string($link) && str_contains($link, '/activate/')));

        $active = $this->client(['invitation_used_at' => now()]);
        $this->actingAs($admin)
            ->get(route('admin.clients.show', $active))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activation.link', null)
                ->where('activation.used_at', fn ($v) => $v !== null));
    }

    public function test_client_dashboard_requires_client_role(): void
    {
        $this->get(route('client.dashboard'))->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('client.dashboard'))->assertForbidden();
    }

    public function test_generic_dashboard_redirects_client_to_panel(): void
    {
        $client = $this->client();

        $this->actingAs($client->user)
            ->get(route('dashboard'))
            ->assertRedirect(route('client.dashboard'));
    }
}

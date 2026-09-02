<?php

namespace Tests\Feature;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function activatedClient(): Client
    {
        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
            'invitation_used_at' => now(),
        ]);
        $client->user->assignRole('client');
        $client->user->update(['password' => 'stare-haslo-123']);

        return $client;
    }

    private function signedShowUrl(Client $client, ?\DateTimeInterface $expires = null): string
    {
        return URL::temporarySignedRoute(
            'client.password-reset.show',
            $expires ?? now()->addDay(),
            ['client' => $client->id],
        );
    }

    private function signedStoreUrl(Client $client): string
    {
        return URL::temporarySignedRoute(
            'client.password-reset.store',
            now()->addDay(),
            ['client' => $client->id],
        );
    }

    public function test_page_loads_with_a_valid_signed_link(): void
    {
        $client = $this->activatedClient();

        $this->get($this->signedShowUrl($client))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ResetClientPassword')
                ->where('client.name', $client->user->name));
    }

    public function test_invalid_signature_shows_link_invalid_page(): void
    {
        $client = $this->activatedClient();

        $this->get(route('client.password-reset.show', $client))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/LinkInvalid'));
    }

    public function test_expired_link_shows_link_invalid_page(): void
    {
        $client = $this->activatedClient();
        $url = $this->signedShowUrl($client, now()->subMinute());

        $this->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/LinkInvalid')
                ->where('message', fn (string $m) => str_contains($m, 'wygasł')));
    }

    public function test_client_sets_a_new_password_and_is_logged_in(): void
    {
        $client = $this->activatedClient();

        $this->post($this->signedStoreUrl($client), [
            'password' => 'nowe-mocne-haslo-77',
            'password_confirmation' => 'nowe-mocne-haslo-77',
        ])->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($client->user->fresh());
        $this->assertTrue(Hash::check('nowe-mocne-haslo-77', $client->user->fresh()->password));
    }

    public function test_password_must_be_confirmed(): void
    {
        $client = $this->activatedClient();

        $this->post($this->signedStoreUrl($client), [
            'password' => 'nowe-mocne-haslo-77',
            'password_confirmation' => 'co-innego',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertTrue(Hash::check('stare-haslo-123', $client->user->fresh()->password));
    }

    public function test_store_rejects_an_unsigned_request(): void
    {
        $client = $this->activatedClient();

        $this->post(route('client.password-reset.store', $client), [
            'password' => 'nowe-mocne-haslo-77',
            'password_confirmation' => 'nowe-mocne-haslo-77',
        ])->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/LinkInvalid'));

        $this->assertTrue(Hash::check('stare-haslo-123', $client->user->fresh()->password));
    }

    public function test_client_card_exposes_reset_link_only_when_login_is_configured(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $activated = $this->activatedClient();
        $fresh = Client::factory()->create(['status' => ClientStatus::Inactive, 'invitation_used_at' => null]);
        $fresh->user->assignRole('client');

        $this->actingAs($admin)->get(route('admin.clients.show', $activated))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('login.reset_link', fn ($v) => is_string($v) && str_contains($v, '/reset-hasla/')));

        $this->actingAs($admin)->get(route('admin.clients.show', $fresh))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('login.reset_link', null));
    }

    public function test_breeze_email_password_reset_is_disabled(): void
    {
        $this->assertFalse(Route::has('password.request'));
        $this->assertFalse(Route::has('password.email'));
        $this->assertFalse(Route::has('password.reset'));
        $this->assertFalse(Route::has('password.store'));

        $this->get('/forgot-password')->assertNotFound();

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('canResetPassword', false));
    }
}

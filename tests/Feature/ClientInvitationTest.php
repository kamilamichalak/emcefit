<?php

namespace Tests\Feature;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Mail\ClientInvitationMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        Mail::fake();
    }

    private function client(string $email = 'anna.kowalska@wp.pl', array $state = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'status' => ClientStatus::Inactive,
            'invitation_used_at' => null,
        ], $state));
        $client->user->update(['email' => $email, 'name' => 'Anna Kowalska']);
        $client->user->assignRole('client');

        return $client->fresh();
    }

    public function test_admin_sends_invitation_with_working_activation_link(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin)
            ->post(route('admin.clients.invitation', $client))
            ->assertRedirect()
            ->assertSessionHas('success');

        $sent = null;
        Mail::assertSent(ClientInvitationMail::class, function (ClientInvitationMail $mail) use (&$sent) {
            $sent = $mail;

            return $mail->hasTo('anna.kowalska@wp.pl');
        });
        $this->assertNotNull($client->fresh()->invitation_sent_at);

        // link z maila otwiera stronę aktywacji
        auth()->logout();
        $this->get($sent->link)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ActivateAccount'));

        // treść maila zawiera imię i link
        $html = $sent->render();
        $this->assertStringContainsString('Cześć Anna!', $html);
        $this->assertStringContainsString(e($sent->link), $html);
    }

    public function test_placeholder_email_is_not_sent(): void
    {
        $client = $this->client('anna.kowalska@test.pl');

        $this->actingAs($this->admin)
            ->post(route('admin.clients.invitation', $client))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertNull($client->fresh()->invitation_sent_at);
    }

    public function test_activated_client_gets_no_invitation(): void
    {
        $client = $this->client(state: ['invitation_used_at' => now(), 'status' => ClientStatus::Active]);

        $this->actingAs($this->admin)
            ->post(route('admin.clients.invitation', $client))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_only_admin_can_send(): void
    {
        $client = $this->client();

        $this->actingAs($client->user)
            ->post(route('admin.clients.invitation', $client))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_client_page_shows_when_invitation_was_sent(): void
    {
        $client = $this->client(state: ['invitation_sent_at' => '2026-11-11 10:30:00']);

        $this->actingAs($this->admin)->get(route('admin.clients.show', $client))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('login.invitation_sent_at', '2026-11-11 10:30'));
    }
}

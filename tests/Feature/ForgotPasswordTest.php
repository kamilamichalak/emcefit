<?php

namespace Tests\Feature;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Mail\ClientInvitationMail;
use App\Mail\ClientPasswordResetMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    private function client(string $email = 'anna.kowalska@wp.pl', bool $activated = true): Client
    {
        $client = Client::factory()->create([
            'status' => $activated ? ClientStatus::Active : ClientStatus::Inactive,
            'invitation_used_at' => $activated ? now() : null,
        ]);
        $client->user->update(['email' => $email, 'name' => 'Anna Kowalska', 'password' => 'stare-haslo-123']);
        $client->user->assignRole('client');

        return $client->fresh();
    }

    private function request(string $email)
    {
        return $this->from(route('password.request'))->post(route('password.email'), ['email' => $email]);
    }

    private function sentResetLink(): string
    {
        $link = null;
        Mail::assertSent(ClientPasswordResetMail::class, function (ClientPasswordResetMail $mail) use (&$link) {
            $link = $mail->link;

            return true;
        });

        return $link;
    }

    public function test_forgot_password_page_is_public(): void
    {
        $this->get('/nie-pamietam-hasla')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_activated_client_gets_reset_link_and_can_set_new_password(): void
    {
        $client = $this->client();

        $this->request('anna.kowalska@wp.pl')
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', ForgotPasswordController::STATUS);

        Mail::assertSent(ClientPasswordResetMail::class, fn ($mail) => $mail->hasTo('anna.kowalska@wp.pl'));
        $link = $this->sentResetLink();

        $this->get($link)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ResetClientPassword'));

        $this->post($link, ['password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!'])
            ->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($client->user);
        $this->assertTrue(Hash::check('NoweHaslo123!', $client->user->fresh()->password));
    }

    public function test_email_is_matched_case_insensitively_and_trimmed(): void
    {
        $this->client();

        $this->request('  Anna.Kowalska@WP.pl ');

        Mail::assertSent(ClientPasswordResetMail::class, fn ($mail) => $mail->hasTo('anna.kowalska@wp.pl'));
    }

    public function test_reset_mail_contains_name_and_link(): void
    {
        $this->client();
        $this->request('anna.kowalska@wp.pl');
        $link = $this->sentResetLink();

        $html = (new ClientPasswordResetMail('Anna Kowalska', $link))->render();

        $this->assertStringContainsString('Cześć Anna!', $html);
        $this->assertStringContainsString(e($link), $html);
        $this->assertStringContainsString('24 godziny', $html);
    }

    public function test_reset_link_expires_after_24_hours(): void
    {
        $this->client();
        $this->request('anna.kowalska@wp.pl');
        $link = $this->sentResetLink();

        $this->travel(25)->hours();

        $this->get($link)->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/LinkInvalid'));
    }

    public function test_not_activated_client_gets_invitation_instead(): void
    {
        $client = $this->client(activated: false);

        $this->request('anna.kowalska@wp.pl')->assertSessionHas('status', ForgotPasswordController::STATUS);

        Mail::assertNotSent(ClientPasswordResetMail::class);
        Mail::assertSent(ClientInvitationMail::class, fn ($mail) => $mail->hasTo('anna.kowalska@wp.pl'));
        $this->assertNotNull($client->fresh()->invitation_sent_at);
    }

    public function test_unknown_email_gets_same_message_and_no_mail(): void
    {
        $this->request('nikt@wp.pl')->assertSessionHas('status', ForgotPasswordController::STATUS);

        Mail::assertNothingSent();
    }

    public function test_placeholder_email_gets_no_mail(): void
    {
        $this->client('anna.kowalska@test.pl');

        $this->request('anna.kowalska@test.pl')->assertSessionHas('status', ForgotPasswordController::STATUS);

        Mail::assertNothingSent();
    }

    public function test_admin_account_gets_no_mail(): void
    {
        $admin = User::factory()->create(['email' => 'szefowa@wp.pl']);
        $admin->assignRole('admin');

        $this->request('szefowa@wp.pl')->assertSessionHas('status', ForgotPasswordController::STATUS);

        Mail::assertNothingSent();
    }

    public function test_email_is_validated(): void
    {
        $this->request('')->assertSessionHasErrors(['email' => 'Podaj adres e-mail.']);
        $this->request('to-nie-mail')->assertSessionHasErrors(['email' => 'Podaj poprawny adres e-mail.']);

        Mail::assertNothingSent();
    }

    public function test_requests_are_throttled(): void
    {
        foreach (range(1, 5) as $i) {
            $this->request('nikt@wp.pl')->assertRedirect();
        }

        $this->request('nikt@wp.pl')->assertStatus(429);
    }

    public function test_logged_in_user_is_redirected_away(): void
    {
        $client = $this->client();

        $this->actingAs($client->user)->get('/nie-pamietam-hasla')->assertRedirect();
    }
}

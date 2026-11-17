<?php

namespace Tests\Feature;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\HelpRequest;
use App\Mail\HelpRequestReceivedMail;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HelpRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        config(['services.support.email' => 'pomoc@example.test']);
    }

    private function send(array $data = [])
    {
        return $this->post(route('help.store'), array_merge([
            'name' => 'Anna Kowalska',
            'email' => 'Anna.Kowalska@WP.pl ',
            'message' => 'Nie widzę swoich zajęć.',
            'from' => 'https://emcefit.com/panel',
        ], $data));
    }

    public function test_help_page_is_public(): void
    {
        $this->get('/pomoc')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Help')->where('prefill.name', ''));
    }

    public function test_guest_request_is_stored_and_mailed_with_reply_to(): void
    {
        $this->send()->assertRedirect(route('help.create'))->assertSessionHas('success');

        $request = HelpRequest::sole();
        $this->assertSame('anna.kowalska@wp.pl', $request->email);
        $this->assertNull($request->user_id);
        $this->assertSame('https://emcefit.com/panel', $request->page_url);

        Mail::assertSent(HelpRequestReceivedMail::class, fn (HelpRequestReceivedMail $mail) => $mail->hasTo('pomoc@example.test')
            && $mail->hasReplyTo('anna.kowalska@wp.pl'));

        $html = (new HelpRequestReceivedMail($request))->render();
        $this->assertStringContainsString('Nie widzę swoich zajęć.', $html);
    }

    public function test_logged_in_client_is_prefilled_and_linked(): void
    {
        $client = Client::factory()->create();
        $client->user->update(['name' => 'Ewa Nowak', 'email' => 'ewa@wp.pl']);
        $client->user->assignRole('client');

        $this->actingAs($client->user)->get('/pomoc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.name', 'Ewa Nowak')
                ->where('prefill.email', 'ewa@wp.pl'));

        $this->actingAs($client->user)->send(['email' => 'ewa@wp.pl']);
        $this->assertSame($client->user->id, HelpRequest::sole()->user_id);
    }

    public function test_placeholder_email_is_not_prefilled(): void
    {
        $client = Client::factory()->create();
        $client->user->update(['email' => 'ewa.nowak@test.pl']);
        $client->user->assignRole('client');

        $this->actingAs($client->user)->get('/pomoc')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefill.email', ''));
    }

    public function test_request_is_stored_even_without_support_email(): void
    {
        config(['services.support.email' => null]);

        $this->send()->assertSessionHas('success');

        $this->assertSame(1, HelpRequest::count());
        Mail::assertNothingSent();
    }

    public function test_validation_messages(): void
    {
        $this->send(['name' => '', 'email' => 'zly', 'message' => ''])
            ->assertSessionHasErrors([
                'name' => 'Podaj imię i nazwisko.',
                'email' => 'Podaj poprawny adres e-mail.',
                'message' => 'Napisz, w czym możemy pomóc.',
            ]);

        $this->assertSame(0, HelpRequest::count());
    }

    public function test_requests_are_throttled(): void
    {
        foreach (range(1, 5) as $i) {
            $this->send()->assertRedirect();
        }

        $this->send()->assertStatus(429);
    }
}

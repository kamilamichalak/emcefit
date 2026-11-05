<?php

namespace Tests\Feature;

use App\Domain\Clients\Models\EmailSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmailSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_is_public_and_shows_no_submissions(): void
    {
        EmailSubmission::create(['full_name' => 'Inna Osoba', 'email' => 'inna@example.com', 'consent_at' => now()]);

        $this->get('/dane')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('EmailSubmission')
                ->missing('submissions'))
            ->assertDontSee('inna@example.com');
    }

    public function test_guest_can_submit_name_and_email(): void
    {
        $this->post('/dane', ['full_name' => '  Anna Nowak ', 'email' => 'Anna.Nowak@Example.com', 'consent' => true])
            ->assertRedirect('/dane')
            ->assertSessionHas('success');

        $row = EmailSubmission::sole();
        $this->assertSame('Anna Nowak', $row->full_name);
        $this->assertSame('anna.nowak@example.com', $row->email);
        $this->assertNotNull($row->consent_at);
    }

    public function test_duplicate_email_is_accepted(): void
    {
        $data = ['full_name' => 'Anna Nowak', 'email' => 'anna@example.com', 'consent' => true];

        $this->post('/dane', $data)->assertSessionHasNoErrors();
        $this->post('/dane', $data)->assertSessionHasNoErrors();

        $this->assertSame(2, EmailSubmission::count());
    }

    public function test_requires_name_valid_email_and_consent(): void
    {
        $this->post('/dane', ['full_name' => '', 'email' => 'nie-email', 'consent' => false])
            ->assertSessionHasErrors(['full_name', 'email', 'consent']);

        $this->assertSame(0, EmailSubmission::count());
    }
}

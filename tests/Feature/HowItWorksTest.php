<?php

namespace Tests\Feature;

use App\Mail\ClientInvitationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HowItWorksTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructions_are_public_and_images_exist(): void
    {
        $this->get('/jak-to-dziala')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('HowItWorks'));

        foreach (['1-zaproszenie', '2-aktywacja', '3-panel', '4-zapisy', '5-zglos', '6-moje-zajecia', '7-wolne-miejsca', '8-pomoc'] as $img) {
            $this->assertFileExists(public_path("images/jak-to-dziala/{$img}.png"));
        }
    }

    public function test_invitation_mail_links_to_instructions(): void
    {
        $html = (new ClientInvitationMail('Anna Kowalska', 'https://example.test/a'))->render();

        $this->assertStringContainsString(route('how-it-works'), $html);
    }
}

<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PaymentInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sees_account_amount_due_deadline_and_regulamin(): void
    {
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00'));
        $client = Client::factory()->create(['invitation_used_at' => now()]);
        $client->user->update(['name' => 'Joanna Przykładowa']);
        $client->user->assignRole('client');
        $nov = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-11-01', 'price_locked' => 100]);
        Payment::factory()->create(['membership_id' => $nov->id, 'client_id' => $client->id, 'amount' => 100, 'status' => PaymentStatus::Settled]);
        Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01', 'price_locked' => 170]);

        $this->actingAs($client->user)->get(route('client.payment-info'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/PaymentInfo')
                ->where('account', config('club.bank_account'))
                ->where('memberships.0.amount_due', 0)
                ->where('memberships.1.amount_due', 170)
                ->where('memberships.1.deadline', '28 listopada')
                ->where('memberships.1.title', 'zajęcia fitness, Joanna Przykładowa, grudzień 2026')
                ->where('regulaminHtml', fn ($html) => strlen($html) > 100));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('client.payment-info'))->assertRedirect(route('login'));
    }
}

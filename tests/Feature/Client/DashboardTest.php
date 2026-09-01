<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function client(): User
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client->user;
    }

    public function test_counter_is_zero_when_no_credits(): void
    {
        $this->actingAs($this->client())
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Dashboard')
                ->where('makeupCredits', 0));
    }

    public function test_counter_sums_only_available_credits_of_this_client(): void
    {
        $user = $this->client();
        $client = $user->client;

        // liczą się: niewykorzystane, świeże (wygasają z końcem tego miesiąca)
        MakeupCredit::factory()->count(2)->for($client)->create();
        // bezterminowe też się liczą
        MakeupCredit::factory()->for($client)->create(['expires_end_of_month' => false]);
        // wykorzystane — nie
        MakeupCredit::factory()->for($client)->used()->create();
        // wygasłe (świeże = false, utworzone w zeszłym miesiącu) — nie
        MakeupCredit::factory()->for($client)->create([
            'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
        ]);
        // cudzy kredyt — nie
        MakeupCredit::factory()->create();

        $this->actingAs($user)
            ->get(route('client.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('makeupCredits', 3));
    }
}

<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Mail\EnrollmentSubmittedMail;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OpenMembershipSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private MembershipType $open;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
        Mail::fake();
        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();
        $this->open = MembershipType::factory()->open(4)->create(['name' => 'Otwarty — 4 wejścia (5 tygodni)', 'price' => 80]);
    }

    private function client(string $email = 'anna@wp.pl'): Client
    {
        $client = Client::factory()->create(['invitation_used_at' => now()]);
        $client->user->update(['email' => $email]);
        $client->user->assignRole('client');

        return $client->fresh();
    }

    public function test_client_can_choose_open_membership_from_the_enrollment_page(): void
    {
        $client = $this->client();

        $this->actingAs($client->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('openOffer.entries', 4)
                ->where('openOffer.available', true)
                ->where('openOffer.deadline', '28 listopada'));

        $this->actingAs($client->user)->post(route('client.enrollment.open'), ['month' => '2026-12'])
            ->assertRedirect();

        $m = Membership::where('client_id', $client->id)->sole();
        $this->assertSame($this->open->id, $m->membership_type_id);
        $this->assertSame('2026-12-01', $m->start_date->toDateString());
        Mail::assertSent(EnrollmentSubmittedMail::class, fn ($mail) => str_contains($mail->render(), 'Wolne miejsca'));

        $this->actingAs($client->user)->get(route('client.enrollment.confirmation', $m))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('isOpen', true));

        // drugi raz na ten sam miesiąc — nie
        $this->actingAs($client->user)->post(route('client.enrollment.open'), ['month' => '2026-12'])
            ->assertSessionHasErrors('class_group_ids');
        $this->assertSame(1, Membership::where('client_id', $client->id)->count());
    }

    public function test_limit_of_open_memberships_per_month(): void
    {
        config(['club.open_membership_limit' => 2]);
        Membership::factory()->count(2)->create(['membership_type_id' => $this->open->id, 'start_date' => '2026-12-01']);
        $client = $this->client();

        $this->actingAs($client->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('openOffer.available', false));

        $this->actingAs($client->user)->post(route('client.enrollment.open'), ['month' => '2026-12'])
            ->assertSessionHasErrors('class_group_ids');
        $this->assertSame(0, Membership::where('client_id', $client->id)->count());
    }

    public function test_not_possible_when_enrollment_closed(): void
    {
        $client = $this->client();

        $this->actingAs($client->user)->post(route('client.enrollment.open'), ['month' => '2027-01'])
            ->assertSessionHasErrors('class_group_ids');
        $this->assertSame(0, Membership::count());
    }
}

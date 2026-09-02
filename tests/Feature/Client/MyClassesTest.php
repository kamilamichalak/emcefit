<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MyClassesTest extends TestCase
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

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::today()->startOfMonth();
    }

    public function test_guest_and_non_client_are_blocked(): void
    {
        $this->get(route('client.classes.index'))->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('client.classes.index'))->assertForbidden();
    }

    public function test_without_a_submission_it_shows_a_prompt_to_enroll(): void
    {
        $this->actingAs($this->client())
            ->get(route('client.classes.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/MyClasses')
                ->where('membership', null)
                ->where('makeupCredits', 0));
    }

    public function test_it_shows_the_submission_for_the_month_with_payment_and_reservation_state(): void
    {
        $user = $this->client();
        $client = $user->client;
        $month = $this->month();

        $type = MembershipType::factory()->create(['price' => 160.00]);
        $group = ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '18:00']);

        $membership = Membership::factory()->create([
            'client_id' => $client->id,
            'membership_type_id' => $type->id,
            'start_date' => $month->toDateString(),
            'end_date' => $month->endOfMonth()->toDateString(),
        ]);
        $membership->classGroups()->attach($group);

        Payment::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $client->id,
            'status' => PaymentStatus::Pending,
        ]);

        $occurrence = ClassSchedule::factory()->create([
            'class_group_id' => $group->id,
            'date' => $month->toDateString(),
            'start_time' => '18:00',
        ]);
        Reservation::factory()->create([
            'membership_id' => $membership->id,
            'client_id' => $client->id,
            'class_schedule_id' => $occurrence->id,
        ]);

        MakeupCredit::factory()->for($client)->create(['expires_end_of_month' => false]);

        $this->actingAs($user)
            ->get(route('client.classes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/MyClasses')
                ->where('makeupCredits', 1)
                ->where('membership.type_name', $type->name)
                ->where('membership.payment_status', 'oczekuje')
                ->has('membership.classes', 1)
                ->has('membership.reservations', 1)
                ->has('membership.reservations.0.type_icon')
                ->has('membership.classes.0.type_icon')
                ->where('membership.reservations.0.status', 'oczekuje_platnosci'));
    }

    public function test_a_client_never_sees_another_clients_submission(): void
    {
        $otherClient = Client::factory()->create();
        Membership::factory()->create([
            'client_id' => $otherClient->id,
            'start_date' => $this->month()->toDateString(),
        ]);

        $this->actingAs($this->client())
            ->get(route('client.classes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('membership', null));
    }
}

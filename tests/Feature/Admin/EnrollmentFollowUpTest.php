<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Mail\PaymentSettledMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EnrollmentFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MembershipType $oneWeekly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00'));
        Mail::fake();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();
        $this->oneWeekly = MembershipType::factory()->create(['sessions_per_week' => 1, 'name' => 'Zamknięty 1x/tydzień — miesięczny', 'price' => 100]);
    }

    private function client(string $name, string $email, bool $activated): Client
    {
        $c = Client::factory()->create(['invitation_used_at' => $activated ? now() : null, 'invitation_sent_at' => now()]);
        $c->user->update(['name' => $name, 'email' => $email]);
        $c->user->assignRole('client');

        return $c->fresh('user');
    }

    private function novemberMembership(Client $client, ClassGroup $group): Membership
    {
        $m = Membership::factory()->create(['client_id' => $client->id, 'membership_type_id' => $this->oneWeekly->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $m->classGroups()->attach($group->id);

        return $m;
    }

    public function test_dashboard_groups_clients_and_one_click_actions_work(): void
    {
        $nov = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-11-01'))->create(['weekday' => 3, 'start_time' => '19:00', 'active_to' => '2026-11-01']);
        $dec = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->create(['weekday' => 3, 'start_time' => '19:00', 'class_type_id' => $nov->class_type_id]);
        $occ = ClassSchedule::factory()->create(['class_group_id' => $dec->id, 'date' => '2026-12-02', 'start_time' => '19:00']);

        $noEmail = $this->client('Bez Maila', 'bez.maila@test.pl', false);
        $notActive = $this->client('Nie Kliknęła', 'nieklik@przyklad.pl', false);
        $active = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        foreach ([$noEmail, $notActive, $active] as $c) {
            $this->novemberMembership($c, $nov);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('followUp.enrolled', 0)
                ->where('followUp.expected', 3)
                ->has('followUp.noEmail', 1)
                ->has('followUp.notActivated', 1)
                ->has('followUp.activeNotEnrolled', 1)
                ->where('followUp.noEmail.0.can_repeat', true));

        // „Zapisz jak w listopadzie” — ta sama grupa w grudniowym grafiku
        $this->actingAs($this->admin)->post(route('admin.clients.enrollment.repeat', $active), ['month' => '2026-12'])
            ->assertSessionHas('success');
        $decMembership = $active->memberships()->whereDate('start_date', '2026-12-01')->sole();
        $this->assertSame([$dec->id], $decMembership->classGroups->pluck('id')->all());
        $this->assertSame($this->admin->id, $decMembership->registered_by_id);

        // drugi raz — nie dubluje
        $this->actingAs($this->admin)->post(route('admin.clients.enrollment.repeat', $active), ['month' => '2026-12'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('followUp.enrolled', 1)
                ->has('followUp.unpaid', 1)
                ->where('followUp.unpaid.0.amount_due', 100));

        // „Zaksięguj” — pełna kwota, zapisy potwierdzone, mail
        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $decMembership))
            ->assertSessionHas('success');
        $decMembership->refresh()->load('payments');
        $this->assertTrue($decMembership->isPaid());
        $this->assertSame(0.0, $decMembership->amountDue());
        $this->assertSame(PaymentStatus::Settled, $decMembership->payments->first()->status);
        $this->assertSame(ReservationStatus::Confirmed, Reservation::where('class_schedule_id', $occ->id)->value('status'));
        Mail::assertSent(PaymentSettledMail::class);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('followUp.paid', 1)->has('followUp.unpaid', 0));
    }

    public function test_non_admin_cannot_use_quick_actions(): void
    {
        $client = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        $m = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01']);

        $this->actingAs($client->user)->post(route('admin.memberships.settle', $m))->assertForbidden();
        $this->actingAs($client->user)->post(route('admin.clients.enrollment.repeat', $client), ['month' => '2026-12'])->assertForbidden();
    }
}

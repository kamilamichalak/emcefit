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

    public function test_settle_with_partial_amount_and_payment_method(): void
    {
        $client = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        $m = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01', 'price_locked' => 170]);

        // za dużo — błąd, nic się nie zapisuje
        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $m), ['amount' => 200, 'method' => 'blik'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, $m->payments()->count());

        // zła forma płatności — błąd
        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $m), ['amount' => 100, 'method' => 'karta'])
            ->assertSessionHasErrors('method');

        // część gotówką → zostaje dopłata
        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $m), ['amount' => 100, 'method' => 'gotowka'])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Zostało do zapłaty 70'));
        $m->refresh()->load('payments');
        $this->assertSame(70.0, $m->amountDue());
        $this->assertSame('gotowka', $m->payments->first()->method);

        // reszta bez podania kwoty → domyślnie cała brakująca (70)
        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $m), ['method' => 'przelew'])
            ->assertSessionHas('success');
        $m->refresh()->load('payments');
        $this->assertSame(0.0, $m->amountDue());
        $this->assertEqualsCanonicalizing(['gotowka', 'przelew'], $m->payments->pluck('method')->all());

        // karta klientki pokazuje formę płatności
        $this->actingAs($this->admin)->get(route('admin.clients.show', $client))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('payments.0.method_label', fn ($l) => in_array($l, ['gotówka', 'przelew'], true)));
    }

    public function test_add_payment_form_saves_method(): void
    {
        $client = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        $m = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01', 'price_locked' => 100]);

        $this->actingAs($this->admin)->post(route('admin.memberships.payments.store', $m), [
            'amount' => 100, 'mark_settled' => true, 'method' => 'blik', 'transfer_title' => 'x',
        ])->assertSessionHasNoErrors();

        $this->assertSame('blik', $m->payments()->first()->method);
    }

    public function test_payment_without_method_defaults_to_transfer(): void
    {
        $client = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        $m = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01', 'price_locked' => 100]);

        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $m))->assertSessionHasNoErrors();

        $this->assertSame('przelew', $m->payments()->first()->method);
    }

    public function test_admin_can_change_payment_method_without_mail(): void
    {
        Mail::fake();
        $client = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        $m = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01', 'price_locked' => 100]);
        $this->actingAs($this->admin)->post(route('admin.memberships.settle', $m));
        $payment = $m->payments()->first();
        $before = count(Mail::queued(\App\Mail\PaymentSettledMail::class)) + count(Mail::sent(\App\Mail\PaymentSettledMail::class));

        $this->actingAs($this->admin)->patch(route('admin.payments.method', $payment), ['method' => 'gotowka'])
            ->assertSessionHasNoErrors();
        $this->assertSame('gotowka', $payment->fresh()->method);

        $this->actingAs($this->admin)->patch(route('admin.payments.method', $payment), ['method' => 'czek'])
            ->assertSessionHasErrors('method');
        $this->assertSame('gotowka', $payment->fresh()->method);

        $after = count(Mail::queued(\App\Mail\PaymentSettledMail::class)) + count(Mail::sent(\App\Mail\PaymentSettledMail::class));
        $this->assertSame($before, $after);

        $this->actingAs($client->user)->patch(route('admin.payments.method', $payment), ['method' => 'blik'])->assertForbidden();
    }

    public function test_non_admin_cannot_use_quick_actions(): void
    {
        $client = $this->client('Ma Konto', 'konto@przyklad.pl', true);
        $m = Membership::factory()->create(['client_id' => $client->id, 'start_date' => '2026-12-01']);

        $this->actingAs($client->user)->post(route('admin.memberships.settle', $m))->assertForbidden();
        $this->actingAs($client->user)->post(route('admin.clients.enrollment.repeat', $client), ['month' => '2026-12'])->assertForbidden();
    }
}

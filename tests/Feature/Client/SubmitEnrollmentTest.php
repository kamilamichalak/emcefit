<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Actions\GenerateMonthlySchedule;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Database\Seeders\ClassTypeSeeder;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SubmitEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ClassTypeSeeder::class);
        $this->seed(MembershipTypeSeeder::class);
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

    private function countWeekday(int $weekdayIso): int
    {
        return collect(CarbonPeriod::create($this->month(), $this->month()->endOfMonth()))
            ->filter(fn ($d) => $d->dayOfWeekIso === $weekdayIso)
            ->count();
    }

    /** @return Collection<int, ClassGroup> */
    private function scheduledGroups(array $weekdays, bool $openEnrollment = true): Collection
    {
        $groups = collect($weekdays)->map(
            fn (int $wd) => ClassGroup::factory()->forMonth($this->month())->create(['weekday' => $wd]),
        );

        app(GenerateMonthlySchedule::class)->handle($this->month());

        if ($openEnrollment) {
            EnrollmentWindow::factory()->forMonth($this->month())->open()->create();
        }

        return $groups;
    }

    public function test_guest_and_non_client_are_blocked(): void
    {
        $this->post(route('client.enrollment.store'))->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->post(route('client.enrollment.store'))->assertForbidden();
    }

    public function test_submission_is_blocked_when_enrollment_window_is_closed(): void
    {
        $groups = $this->scheduledGroups([1, 3], openEnrollment: false);

        $this->actingAs($this->client())->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ])->assertSessionHasErrors('class_group_ids');

        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_create_page_reports_enrollment_window_state(): void
    {
        $this->scheduledGroups([1], openEnrollment: false);

        $this->actingAs($this->client())->get(route('client.enrollment.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('enrollmentOpen', false));

        EnrollmentWindow::factory()->forMonth($this->month())->open()->create();

        $this->actingAs($this->client())->get(route('client.enrollment.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('enrollmentOpen', true));
    }

    public function test_client_submits_two_classes_and_gets_a_monthly_membership(): void
    {
        $groups = $this->scheduledGroups([1, 3]); // pon + śr
        $user = $this->client();

        $response = $this->actingAs($user)->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ]);

        $membership = $user->client->memberships()->sole();
        $response->assertRedirect(route('client.enrollment.confirmation', $membership));

        $this->assertSame('Zamknięty 2x/tydzień — miesięczny', $membership->membershipType->name);
        $this->assertSame($this->month()->toDateString(), $membership->start_date->toDateString());
        $this->assertSame($this->month()->endOfMonth()->toDateString(), $membership->end_date->toDateString());
        $this->assertCount(2, $membership->classGroups);

        $expected = $this->countWeekday(1) + $this->countWeekday(3);
        $this->assertDatabaseCount('reservations', $expected);
        $this->assertSame($expected, $membership->reservations()->where('status', 'oczekuje_platnosci')->count());
        $this->assertDatabaseCount('makeup_credits', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_membership_snapshots_the_price_at_signup_and_ignores_later_price_edits(): void
    {
        $groups = $this->scheduledGroups([1, 3]); // 2x/tydz. → „Zamknięty 2x/tydzień — miesięczny" = 160
        $user = $this->client();

        $this->actingAs($user)->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ])->assertRedirect();

        $membership = $user->client->memberships()->sole();
        $this->assertSame('160.00', $membership->price_locked);

        // Prompt 11: edycja cennika NIE zmienia ceny wystawionego karnetu
        $membership->membershipType->update(['price' => '999.00']);
        $this->assertSame('160.00', $membership->fresh()->price_locked);
    }

    public function test_planned_absence_becomes_cancelled_reservation_plus_makeup_credit(): void
    {
        $groups = $this->scheduledGroups([2]); // wt
        $user = $this->client();
        $skip = ClassSchedule::query()->whereIn('class_group_id', $groups->pluck('id'))->orderBy('date')->first();

        $this->actingAs($user)->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [$skip->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', ['class_schedule_id' => $skip->id, 'status' => 'zwolnione']);
        $this->assertDatabaseCount('makeup_credits', 1);
        $credit = MakeupCredit::sole();
        $this->assertSame($user->client->id, $credit->client_id);
        $this->assertTrue($credit->expires_end_of_month);
        $this->assertFalse($credit->used);
        $this->assertSame($this->countWeekday(2) - 1, Reservation::where('status', 'oczekuje_platnosci')->count());
    }

    public function test_club_cancelled_occurrence_also_yields_makeup_credit(): void
    {
        $groups = $this->scheduledGroups([4]); // czw
        ClassSchedule::query()->whereIn('class_group_id', $groups->pluck('id'))->orderBy('date')->first()
            ->update(['status' => ClassOccurrenceStatus::Cancelled, 'cancellation_reason' => 'Święto']);

        $this->actingAs($this->client())->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [], // klient nic nie odznaczył
        ])->assertRedirect();

        $this->assertDatabaseCount('makeup_credits', 1);
        $this->assertSame(1, Reservation::where('status', 'zwolnione')->count());
    }

    public function test_count_without_a_pricing_variant_is_rejected(): void
    {
        $groups = $this->scheduledGroups([1, 2, 3, 4, 5]); // 5 zajęć — brak wariantu (cennik 1..4)

        $this->actingAs($this->client())->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ])->assertSessionHasErrors('class_group_ids');

        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_second_submission_for_the_same_month_is_rejected(): void
    {
        $groups = $this->scheduledGroups([1]);
        $user = $this->client();
        $payload = [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ];

        $this->actingAs($user)->post(route('client.enrollment.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('client.enrollment.store'), $payload)->assertSessionHasErrors('class_group_ids');

        $this->assertCount(1, $user->client->memberships);
    }

    public function test_confirmation_is_scoped_to_the_owner(): void
    {
        $groups = $this->scheduledGroups([1]);
        $owner = $this->client();
        $this->actingAs($owner)->post(route('client.enrollment.store'), [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ]);
        $membership = $owner->client->memberships()->sole();

        $this->actingAs($owner)->get(route('client.enrollment.confirmation', $membership))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/EnrollmentConfirmation')
                ->where('bank.account', config('club.bank_account'))
                ->where('pendingCount', $this->countWeekday(1)));

        $this->actingAs($this->client())->get(route('client.enrollment.confirmation', $membership))
            ->assertForbidden();
    }

    public function test_skipping_whole_weeks_downgrades_to_a_shorter_pack(): void
    {
        CarbonImmutable::setTestNow('2026-06-15'); // czerwiec 2026 ma 5 poniedziałków

        try {
            $month = CarbonImmutable::parse('2026-06-01');
            $groups = collect(range(0, 2))->map(
                fn (int $i) => ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => "1{$i}:00"]),
            );
            app(GenerateMonthlySchedule::class)->handle($month);
            EnrollmentWindow::factory()->forMonth($month)->open()->create();

            $user = $this->client();

            // pomiń całe tygodnie 2 i 3 (poniedziałki 8 i 15 czerwca) we wszystkich grupach
            $absent = ClassSchedule::query()
                ->whereIn('date', ['2026-06-08', '2026-06-15'])
                ->pluck('id')->all();

            $this->actingAs($user)->post(route('client.enrollment.store'), [
                'month' => '2026-06',
                'class_group_ids' => $groups->pluck('id')->all(),
                'absences' => $absent,
            ])->assertRedirect();

            $membership = $user->client->memberships()->sole();

            $this->assertSame('Zamknięty 3x/tydzień — 3 tygodnie', $membership->membershipType->name);
            $this->assertSame('2026-06-01', $membership->start_date->toDateString());
            $this->assertSame('2026-06-01', $membership->first_entry_date->toDateString());
            // data_do = ostatnie "będę" (29 czerwca), nie "pierwsze wejście + 3 tygodnie"
            $this->assertSame('2026-06-29', $membership->end_date->toDateString());

            // okno 1–29 czerwca: 5 poniedziałków × 3 grupy = 15 rezerwacji
            $this->assertDatabaseCount('reservations', 15);
            $this->assertSame(9, $membership->reservations()->where('status', 'oczekuje_platnosci')->count());
            $this->assertSame(6, $membership->reservations()->where('status', 'zwolnione')->count());
            $this->assertDatabaseCount('makeup_credits', 6);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_create_page_exposes_occurrences_per_group(): void
    {
        $groups = $this->scheduledGroups([1]);

        $this->actingAs($this->client())->get(route('client.enrollment.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('scheduleGenerated', true)
                ->has('occurrencesByGroup.'.$groups->first()->id, $this->countWeekday(1)));
    }
}

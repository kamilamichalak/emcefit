<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reservations\Actions\BookFreeSpot;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Reservations\SeatHolds;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Domain\Scheduling\Models\ClassType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Rezerwacja miejsc dla stałych uczestniczek — przypadki brzegowe (prośba Kamili 25.11).
 */
class SeatHoldsEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private ClassType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->type = ClassType::factory()->create();
        foreach ([1, 2] as $n) {
            MembershipType::factory()->create(['sessions_per_week' => $n, 'name' => "Zamknięty {$n}x/tydzień — miesięczny", 'price' => 100 * $n]);
        }
    }

    private function at(string $datetime): void
    {
        $this->travelTo(CarbonImmutable::parse($datetime));
        $this->app->forgetScopedInstances();
    }

    private function group(string $month, int $weekday, string $time, int $capacity = 3, ?ClassType $type = null): ClassGroup
    {
        $m = CarbonImmutable::parse($month.'-01');

        return ClassGroup::factory()->forMonth($m)->create([
            'weekday' => $weekday, 'start_time' => $time, 'capacity' => $capacity,
            'class_type_id' => ($type ?? $this->type)->id, 'active_to' => $m->toDateString(),
        ]);
    }

    private function occurrence(ClassGroup $group, string $date): ClassSchedule
    {
        return ClassSchedule::factory()->create(['class_group_id' => $group->id, 'date' => $date, 'start_time' => $group->start_time]);
    }

    private function client(): Client
    {
        $c = Client::factory()->create(['invitation_used_at' => now()]);
        $c->user->assignRole('client');

        return $c->fresh('user');
    }

    private function regular(ClassGroup $group, string $month): Client
    {
        $c = $this->client();
        $m = Membership::factory()->create(['client_id' => $c->id, 'start_date' => $month.'-01']);
        $m->classGroups()->attach($group->id);

        return $c;
    }

    private function free(ClassSchedule $occ): int
    {
        return app(FreeSpots::class)->freeSeats($occ->fresh(['classGroup', 'reservations']));
    }

    private function holds(ClassGroup $group, string $month, ?int $except = null, bool $unpaid = false): int
    {
        return app(SeatHolds::class)->count($group, CarbonImmutable::parse($month.'-01'), $except, $unpaid);
    }

    public function test_hold_lasts_until_the_last_minute_of_the_month_and_ends_at_midnight(): void
    {
        $nov = $this->group('2026-11', 1, '17:55');
        $dec = $this->group('2026-12', 1, '17:55');
        $occ = $this->occurrence($dec, '2026-12-07');
        $this->regular($nov, '2026-11');
        $this->regular($nov, '2026-11');

        $this->at('2026-11-30 23:59');
        $this->assertSame(1, $this->free($occ));

        $this->at('2026-12-01 00:00');
        $this->assertSame(3, $this->free($occ));
    }

    public function test_works_across_the_year_boundary_december_to_january(): void
    {
        $dec = $this->group('2026-12', 3, '19:00');
        $jan = $this->group('2027-01', 3, '19:00');
        $occ = $this->occurrence($jan, '2027-01-13');
        $this->regular($dec, '2026-12');

        $this->at('2026-12-28 10:00');
        $this->assertSame(1, $this->holds($jan, '2027-01'));
        $this->assertSame(2, $this->free($occ));

        $this->at('2027-01-01 08:00');
        $this->assertSame(0, $this->holds($jan, '2027-01'));
    }

    public function test_hold_only_affects_the_same_class_not_other_classes(): void
    {
        $this->at('2026-11-25 10:00');
        $novMon = $this->group('2026-11', 1, '17:55');
        $decMon = $this->group('2026-12', 1, '17:55');
        $decMonLater = $this->group('2026-12', 1, '19:00');                 // inna godzina
        $decTue = $this->group('2026-12', 2, '17:55');                      // inny dzień
        $decOtherType = $this->group('2026-12', 1, '17:55', 3, ClassType::factory()->create()); // inny rodzaj
        $this->regular($novMon, '2026-11');

        $this->assertSame(1, $this->holds($decMon, '2026-12'));
        $this->assertSame(0, $this->holds($decMonLater, '2026-12'));
        $this->assertSame(0, $this->holds($decTue, '2026-12'));
        $this->assertSame(0, $this->holds($decOtherType, '2026-12'));
    }

    public function test_class_moved_to_another_hour_in_the_new_schedule_holds_nothing(): void
    {
        $this->at('2026-11-25 10:00');
        $nov = $this->group('2026-11', 4, '17:55');
        $dec = $this->group('2026-12', 4, '18:00');   // Majka przesunęła godzinę
        $this->regular($nov, '2026-11');

        $this->assertSame(0, $this->holds($dec, '2026-12'));
    }

    public function test_enrolled_unpaid_regular_keeps_hold_on_new_choice_and_paid_one_counts_once(): void
    {
        $this->at('2026-11-25 10:00');
        $novA = $this->group('2026-11', 1, '17:55');
        $decA = $this->group('2026-12', 1, '17:55');
        $decB = $this->group('2026-12', 2, '20:05');
        $paid = $this->regular($novA, '2026-11');
        $switcher = $this->regular($novA, '2026-11');

        // zapłacona: zapis na A w grudniu — nie jest już „trzymana”, zajmuje miejsce normalnie
        $mPaid = Membership::factory()->create(['client_id' => $paid->id, 'start_date' => '2026-12-01']);
        $mPaid->classGroups()->attach($decA->id);
        Payment::factory()->create(['membership_id' => $mPaid->id, 'client_id' => $paid->id, 'status' => PaymentStatus::Settled]);

        // zapisana bez wpłaty, ale na inne zajęcia (B) — trzymamy B, nie A
        $mSw = Membership::factory()->create(['client_id' => $switcher->id, 'start_date' => '2026-12-01']);
        $mSw->classGroups()->attach($decB->id);

        $this->assertSame(0, $this->holds($decA, '2026-12', unpaid: true));
        $this->assertSame(1, $this->holds($decB, '2026-12', unpaid: true));
        // w „Wolnych miejscach” zapis bez wpłaty i tak liczy się jako zajęte — nie liczymy go drugi raz
        $this->assertSame(0, $this->holds($decB, '2026-12', unpaid: false));
    }

    public function test_previous_open_membership_holds_no_seats(): void
    {
        $this->at('2026-11-25 10:00');
        $dec = $this->group('2026-12', 1, '17:55');
        $c = $this->client();
        Membership::factory()->create([
            'client_id' => $c->id, 'start_date' => '2026-11-01',
            'membership_type_id' => MembershipType::factory()->open(4)->create()->id,
        ]);

        $this->assertSame(0, $this->holds($dec, '2026-12'));
    }

    public function test_current_month_classes_are_not_affected(): void
    {
        $this->at('2026-11-25 10:00');
        $oct = $this->group('2026-10', 1, '17:55');
        $nov = $this->group('2026-11', 1, '17:55');
        $occ = $this->occurrence($nov, '2026-11-30');
        $this->regular($oct, '2026-10');   // „stała” z października bez listopada

        // listopad już trwa — żadnych trzymanych miejsc
        $this->assertSame(3, $this->free($occ));
    }

    public function test_free_seats_never_go_below_zero(): void
    {
        $this->at('2026-11-25 10:00');
        $nov = $this->group('2026-11', 1, '17:55', 5);
        $dec = $this->group('2026-12', 1, '17:55', 2);   // mniejsza sala w grudniu
        $occ = $this->occurrence($dec, '2026-12-07');
        foreach (range(1, 4) as $i) {
            $this->regular($nov, '2026-11');
        }

        $this->assertSame(0, $this->free($occ));
    }

    public function test_open_pass_booking_on_a_held_class_goes_to_waitlist_not_a_seat(): void
    {
        $this->at('2026-11-25 10:00');
        $nov = $this->group('2026-11', 1, '17:55', 1);
        $dec = $this->group('2026-12', 1, '17:55', 1);
        $occ = $this->occurrence($dec, '2026-12-07');
        $this->regular($nov, '2026-11');

        $outsider = $this->client();
        Membership::factory()->create([
            'client_id' => $outsider->id, 'start_date' => '2026-12-01',
            'membership_type_id' => MembershipType::factory()->open(4)->create()->id,
        ]);

        $reservation = app(BookFreeSpot::class)->handle($outsider, $occ);

        $this->assertSame(ReservationStatus::Waitlist, $reservation->status);
    }

    public function test_admin_can_still_enroll_over_the_limit(): void
    {
        $this->at('2026-11-25 10:00');
        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();
        $nov = $this->group('2026-11', 1, '17:55', 1);
        $dec = $this->group('2026-12', 1, '17:55', 1);
        $this->occurrence($dec, '2026-12-07');
        $this->regular($nov, '2026-11');
        $newcomer = $this->client();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // klientka sama — nie
        $this->actingAs($newcomer->user)->post(route('client.enrollment.store'), ['month' => '2026-12', 'class_group_ids' => [$dec->id], 'absences' => []])
            ->assertSessionHasErrors('class_group_ids');

        // Majka w jej imieniu — świadomie ponad limit, tak
        $this->actingAs($admin)->post(route('admin.clients.enrollment.store', $newcomer), ['month' => '2026-12', 'class_group_ids' => [$dec->id], 'absences' => []])
            ->assertSessionHasNoErrors();
        $this->assertTrue($newcomer->memberships()->whereDate('start_date', '2026-12-01')->exists());
    }

    public function test_regular_with_two_classes_holds_both_and_sees_her_own_seats(): void
    {
        $this->at('2026-11-25 10:00');
        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();
        $novMon = $this->group('2026-11', 1, '17:55', 1);
        $novWed = $this->group('2026-11', 3, '17:55', 1);
        $decMon = $this->group('2026-12', 1, '17:55', 1);
        $decWed = $this->group('2026-12', 3, '17:55', 1);
        $this->occurrence($decMon, '2026-12-07');
        $this->occurrence($decWed, '2026-12-09');
        $c = $this->client();
        $m = Membership::factory()->create(['client_id' => $c->id, 'start_date' => '2026-11-01']);
        $m->classGroups()->attach([$novMon->id, $novWed->id]);

        $this->actingAs($c->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('classGroups.0.free_spots', 1)
                ->where('classGroups.1.free_spots', 1));

        $this->actingAs($c->user)->post(route('client.enrollment.store'), ['month' => '2026-12', 'class_group_ids' => [$decMon->id, $decWed->id], 'absences' => []])
            ->assertSessionHasNoErrors();
    }
}

<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Reservations\FreeSpots;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SeatHoldsTest extends TestCase
{
    use RefreshDatabase;

    private ClassGroup $dec;

    private ClassSchedule $occ;

    private Client $regular;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00'));
        EnrollmentWindow::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->open()->create();
        MembershipType::factory()->create(['sessions_per_week' => 1, 'name' => 'Zamknięty 1x/tydzień — miesięczny', 'price' => 100]);

        $nov = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-11-01'))->create(['weekday' => 2, 'start_time' => '20:05', 'capacity' => 2, 'active_to' => '2026-11-01']);
        $this->dec = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-12-01'))->create(['weekday' => 2, 'start_time' => '20:05', 'capacity' => 2, 'class_type_id' => $nov->class_type_id]);
        $this->occ = ClassSchedule::factory()->create(['class_group_id' => $this->dec->id, 'date' => '2026-12-01', 'start_time' => '20:05']);

        // dwie stałe z listopada, obie jeszcze bez zapisu na grudzień → grupa „pełna” dla innych
        foreach ([1, 2] as $i) {
            $c = $this->client();
            $m = Membership::factory()->create(['client_id' => $c->id, 'start_date' => '2026-11-01']);
            $m->classGroups()->attach($nov->id);
            $i === 1 && $this->regular = $c;
        }
    }

    private function client(): Client
    {
        $c = Client::factory()->create(['invitation_used_at' => now()]);
        $c->user->assignRole('client');

        return $c->fresh('user');
    }

    public function test_regulars_seats_are_held_until_the_month_starts(): void
    {
        $newcomer = $this->client();

        // nowa osoba nie widzi wolnych miejsc i nie może się zapisać
        $this->actingAs($newcomer->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('classGroups.0.free_spots', 0));
        $this->actingAs($newcomer->user)->post(route('client.enrollment.store'), ['month' => '2026-12', 'class_group_ids' => [$this->dec->id], 'absences' => []])
            ->assertSessionHasErrors('class_group_ids');

        // stała uczestniczka widzi swoje miejsce i może się zapisać
        $this->actingAs($this->regular->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('classGroups.0.free_spots', 1));
        $this->actingAs($this->regular->user)->post(route('client.enrollment.store'), ['month' => '2026-12', 'class_group_ids' => [$this->dec->id], 'absences' => []])
            ->assertSessionHasNoErrors();

        // zapisała się, ale nie zapłaciła — nadal trzyma miejsce przed nową osobą
        $this->actingAs($newcomer->user)->get(route('client.enrollment.create', ['month' => '2026-12']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('classGroups.0.free_spots', 0));

        // „wolne miejsca” (karnet otwarty itp.) też nie pokazują trzymanych miejsc
        $this->assertSame(0, app(FreeSpots::class)->freeSeats($this->occ->fresh(['classGroup', 'reservations'])));
    }

    public function test_hold_expires_on_the_first_day_of_the_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-01 08:00'));
        $this->app->forgetScopedInstances();

        $this->assertSame(2, app(FreeSpots::class)->freeSeats($this->occ->fresh(['classGroup', 'reservations'])));
    }
}

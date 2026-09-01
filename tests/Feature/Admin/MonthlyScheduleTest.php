<?php

namespace Tests\Feature\Admin;

use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MonthlyScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-06-01');
    }

    private function countWeekdayInMonth(CarbonImmutable $month, int $weekdayIso): int
    {
        return collect(CarbonPeriod::create($month->startOfMonth(), $month->endOfMonth()))
            ->filter(fn ($date) => $date->dayOfWeekIso === $weekdayIso)
            ->count();
    }

    public function test_non_admin_cannot_view_or_generate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.schedule.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.schedule.generate'), ['month' => '2026-06'])
            ->assertForbidden();
    }

    public function test_index_reports_no_schedule_when_none_generated(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.schedule.index', ['month' => '2026-06']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Schedule/Index')
                ->where('generated', false)
                ->has('occurrences', 0));
    }

    public function test_generate_clones_each_pattern_weekday_across_the_month(): void
    {
        $month = $this->month();
        // wzorzec: poniedziałki 18:00 + środy 09:00
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '18:00']);
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 3, 'start_time' => '09:00']);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m')])
            ->assertRedirect()
            ->assertSessionHas('success');

        $expected = $this->countWeekdayInMonth($month, 1) + $this->countWeekdayInMonth($month, 3);
        $this->assertSame($expected, ClassSchedule::count());

        $mondayOccurrence = ClassSchedule::query()
            ->whereHas('classGroup', fn ($q) => $q->where('weekday', 1))
            ->first();
        $this->assertSame('18:00', $mondayOccurrence->startsAt());
        $this->assertSame(ClassOccurrenceStatus::Planned, $mondayOccurrence->status);
        $this->assertSame(1, CarbonImmutable::parse($mondayOccurrence->date)->dayOfWeekIso);
    }

    public function test_generating_twice_without_regenerate_does_not_duplicate(): void
    {
        $month = $this->month();
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 2, 'start_time' => '17:00']);

        $this->actingAs($this->admin())->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m')]);
        $countAfterFirst = ClassSchedule::count();

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m')])
            ->assertSessionHas('warning');

        $this->assertSame($countAfterFirst, ClassSchedule::count());
    }

    public function test_regenerate_rebuilds_planned_occurrences_from_current_pattern(): void
    {
        $month = $this->month();
        $group = ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '19:00']);

        $this->actingAs($this->admin())->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m')]);
        $this->assertSame($this->countWeekdayInMonth($month, 1), ClassSchedule::count());

        // wzorzec sie zmienia — te same zajecia przenosza sie z poniedzialkow na wtorki
        $group->update(['weekday' => 2]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m'), 'regenerate' => true])
            ->assertSessionHas('success');

        // stare (poniedzialkowe) wystapienia usuniete, nowe (wtorkowe) zbudowane
        $this->assertSame($this->countWeekdayInMonth($month, 2), ClassSchedule::count());
        $this->assertSame(
            0,
            ClassSchedule::get()->filter(fn ($o) => CarbonImmutable::parse($o->date)->dayOfWeekIso === 1)->count(),
        );
    }

    public function test_regenerate_keeps_cancelled_occurrences(): void
    {
        $month = $this->month();
        $group = ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '18:00']);

        $firstMonday = collect(CarbonPeriod::create($month->startOfMonth(), $month->endOfMonth()))
            ->first(fn ($date) => $date->dayOfWeekIso === 1);

        ClassSchedule::factory()->cancelled('Święto')->create([
            'class_group_id' => $group->id,
            'date' => $firstMonday->toDateString(),
            'start_time' => '18:00',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m'), 'regenerate' => true]);

        $this->assertDatabaseHas('class_schedule', [
            'class_group_id' => $group->id,
            'date' => $firstMonday->toDateString(),
            'status' => ClassOccurrenceStatus::Cancelled->value,
        ]);
        // reszta poniedziałków = planowane, bez duplikatu pierwszego
        $this->assertSame($this->countWeekdayInMonth($month, 1), ClassSchedule::count());
    }

    public function test_generate_without_pattern_shows_error(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.schedule.generate'), ['month' => '2026-06'])
            ->assertSessionHas('error');

        $this->assertSame(0, ClassSchedule::count());
    }
}

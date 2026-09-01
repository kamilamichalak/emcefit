<?php

namespace Tests\Feature\Admin;

use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Domain\Trainers\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyPatternToNextMonthTest extends TestCase
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
        return CarbonImmutable::parse('2026-09-01');
    }

    public function test_non_admin_cannot_copy(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.class-groups.copy-to-next-month'), ['month' => '2026-09'])
            ->assertForbidden();
    }

    public function test_copy_closes_current_rows_and_creates_next_month_copies(): void
    {
        $month = $this->month();
        $trainer = Trainer::factory()->create();
        $a = ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '18:00', 'capacity' => 20, 'trainer_id' => $trainer->id]);
        $b = ClassGroup::factory()->forMonth($month)->create(['weekday' => 3, 'start_time' => '09:00', 'capacity' => 15]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy-to-next-month'), ['month' => $month->format('Y-m')])
            ->assertRedirect(route('admin.class-groups.index', ['month' => '2026-10']))
            ->assertSessionHas('success');

        // stare wiersze zamkniete koncem wrzesnia
        $this->assertSame('2026-09-01', $a->refresh()->active_to->toDateString());
        $this->assertSame('2026-09-01', $b->refresh()->active_to->toDateString());

        // nowe wiersze na pazdziernik, otwarte, wierna kopia
        $copies = ClassGroup::whereDate('active_from', '2026-10-01')->orderBy('weekday')->get();
        $this->assertCount(2, $copies);
        $this->assertTrue($copies->every(fn ($g) => $g->active_to === null));
        $this->assertSame(1, $copies[0]->weekday->value);
        $this->assertSame('18:00', $copies[0]->startsAt());
        $this->assertSame($trainer->id, $copies[0]->trainer_id);
        $this->assertSame(15, $copies[1]->capacity);

        $this->assertSame(4, ClassGroup::count());
    }

    public function test_copy_does_not_touch_generated_schedule(): void
    {
        $month = $this->month();
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 1]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy-to-next-month'), ['month' => $month->format('Y-m')]);

        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_conflict_when_next_month_pattern_exists_and_no_force(): void
    {
        $month = $this->month();
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 1]);
        $existing = ClassGroup::factory()->forMonth($month->addMonthNoOverflow())->create(['weekday' => 2]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy-to-next-month'), ['month' => $month->format('Y-m')])
            ->assertSessionHas('warning');

        // nic nie skopiowane, istniejacy wzorzec pazdziernikowy nietkniety
        $this->assertSame(2, ClassGroup::count());
        $this->assertNull($existing->refresh()->active_to);
    }

    public function test_force_overwrites_the_existing_next_month_pattern(): void
    {
        $month = $this->month();
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '18:00']);
        $stale = ClassGroup::factory()->forMonth($month->addMonthNoOverflow())->create(['weekday' => 5]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy-to-next-month'), [
                'month' => $month->format('Y-m'),
                'force' => true,
            ])
            ->assertRedirect(route('admin.class-groups.index', ['month' => '2026-10']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('class_groups', ['id' => $stale->id]);
        $october = ClassGroup::whereDate('active_from', '2026-10-01')->get();
        $this->assertCount(1, $october);
        $this->assertSame(1, $october->first()->weekday->value);
    }

    public function test_empty_source_pattern_yields_an_error(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy-to-next-month'), ['month' => '2026-09'])
            ->assertSessionHas('error');

        $this->assertSame(0, ClassGroup::count());
    }
}

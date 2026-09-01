<?php

namespace Tests\Feature\Admin;

use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Domain\Trainers\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CopyPatternTest extends TestCase
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

    private function september(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-01');
    }

    public function test_non_admin_cannot_copy(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.class-groups.copy'), ['month' => '2026-10'])
            ->assertForbidden();
    }

    public function test_future_month_shows_the_pattern_as_inherited_and_read_only(): void
    {
        ClassGroup::factory()->forMonth($this->september())->count(2)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.index', ['month' => '2026-11']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ClassGroups/Index')
                ->where('patternInherited', true)
                ->where('inheritedFromLabel', 'wrzesień 2026')
                ->has('groups', 2)
                ->where('groups.0.inherited', true));
    }

    public function test_own_month_is_editable(): void
    {
        ClassGroup::factory()->forMonth($this->september())->create();

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.index', ['month' => '2026-09']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('patternInherited', false)
                ->where('groups.0.inherited', false));
    }

    public function test_copy_into_month_closes_inherited_rows_and_creates_own_copies(): void
    {
        $trainer = Trainer::factory()->create();
        $a = ClassGroup::factory()->forMonth($this->september())->create(['weekday' => 1, 'start_time' => '18:00', 'trainer_id' => $trainer->id]);
        $b = ClassGroup::factory()->forMonth($this->september())->create(['weekday' => 3, 'start_time' => '09:00', 'capacity' => 15]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy'), ['month' => '2026-10'])
            ->assertRedirect(route('admin.class-groups.index', ['month' => '2026-10']))
            ->assertSessionHas('success');

        // wiersze zrodlowe zamkniete miesiacem przed docelowym (wrzesien)
        $this->assertSame('2026-09-01', $a->refresh()->active_to->toDateString());
        $this->assertSame('2026-09-01', $b->refresh()->active_to->toDateString());

        $copies = ClassGroup::whereDate('active_from', '2026-10-01')->orderBy('weekday')->get();
        $this->assertCount(2, $copies);
        $this->assertTrue($copies->every(fn ($g) => $g->active_to === null));
        $this->assertSame('18:00', $copies[0]->startsAt());
        $this->assertSame($trainer->id, $copies[0]->trainer_id);
        $this->assertSame(15, $copies[1]->capacity);
        $this->assertSame(4, ClassGroup::count());
    }

    public function test_october_view_is_own_and_september_view_unchanged_after_copy(): void
    {
        ClassGroup::factory()->forMonth($this->september())->create(['weekday' => 2]);

        $this->actingAs($this->admin())->post(route('admin.class-groups.copy'), ['month' => '2026-10']);

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.index', ['month' => '2026-10']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('patternInherited', false)->has('groups', 1));

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.index', ['month' => '2026-09']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('patternInherited', false)->has('groups', 1));
    }

    public function test_copy_does_not_generate_schedule(): void
    {
        ClassGroup::factory()->forMonth($this->september())->create();

        $this->actingAs($this->admin())->post(route('admin.class-groups.copy'), ['month' => '2026-10']);

        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_conflict_when_target_month_already_has_its_own_pattern(): void
    {
        ClassGroup::factory()->forMonth($this->september())->create(['weekday' => 1]);
        $own = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-10-01'))->create(['weekday' => 2]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy'), ['month' => '2026-10'])
            ->assertSessionHas('warning');

        $this->assertSame(2, ClassGroup::count());
        $this->assertNull($own->refresh()->active_to);
    }

    public function test_force_overwrites_target_own_pattern(): void
    {
        ClassGroup::factory()->forMonth($this->september())->create(['weekday' => 1]);
        $stale = ClassGroup::factory()->forMonth(CarbonImmutable::parse('2026-10-01'))->create(['weekday' => 5]);

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy'), ['month' => '2026-10', 'force' => true])
            ->assertRedirect(route('admin.class-groups.index', ['month' => '2026-10']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('class_groups', ['id' => $stale->id]);
        $october = ClassGroup::whereDate('active_from', '2026-10-01')->get();
        $this->assertCount(1, $october);
        $this->assertSame(1, $october->first()->weekday->value);
    }

    public function test_empty_when_nothing_to_copy(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.copy'), ['month' => '2026-10'])
            ->assertSessionHas('error');

        $this->assertSame(0, ClassGroup::count());
    }
}

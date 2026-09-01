<?php

namespace Tests\Feature\Admin;

use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassType;
use App\Domain\Trainers\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClassGroupPatternTest extends TestCase
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

    private function thisMonth(): string
    {
        return CarbonImmutable::today()->format('Y-m');
    }

    public function test_non_admin_cannot_view_the_pattern(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.class-groups.index'))
            ->assertForbidden();
    }

    public function test_index_shows_only_groups_active_for_the_selected_month(): void
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $thisMonth->addMonthNoOverflow();

        ClassGroup::factory()->forMonth($thisMonth)->create();
        ClassGroup::factory()->forMonth($nextMonth)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.index', ['month' => $thisMonth->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ClassGroups/Index')
                ->where('month.value', $thisMonth->format('Y-m'))
                ->has('groups', 1));

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.index', ['month' => $nextMonth->format('Y-m')]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('groups', 2));
    }

    public function test_admin_adds_a_class_to_the_pattern(): void
    {
        $type = ClassType::factory()->create(['default_capacity' => 18]);
        $trainer = Trainer::factory()->create();
        $month = CarbonImmutable::today()->startOfMonth();

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.store'), [
                'month' => $month->format('Y-m'),
                'class_type_id' => $type->id,
                'trainer_id' => $trainer->id,
                'weekday' => 3,
                'start_time' => '17:30',
                'duration_minutes' => 55,
                'capacity' => 15,
            ])
            ->assertRedirect(route('admin.class-groups.index', ['month' => $month->format('Y-m')]));

        $group = ClassGroup::sole();
        $this->assertSame($type->id, $group->class_type_id);
        $this->assertSame($trainer->id, $group->trainer_id);
        $this->assertSame(3, $group->weekday->value);
        $this->assertSame('17:30', $group->startsAt());
        $this->assertSame(15, $group->capacity);
        $this->assertSame($month->toDateString(), $group->active_from->toDateString());
        $this->assertNull($group->active_to);
    }

    public function test_trainer_is_optional(): void
    {
        $type = ClassType::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.store'), [
                'month' => $this->thisMonth(),
                'class_type_id' => $type->id,
                'weekday' => 1,
                'start_time' => '09:00',
                'duration_minutes' => 55,
                'capacity' => 20,
            ])
            ->assertRedirect();

        $this->assertNull(ClassGroup::sole()->trainer_id);
    }

    public function test_weekday_must_be_a_workday(): void
    {
        $type = ClassType::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.store'), [
                'month' => $this->thisMonth(),
                'class_type_id' => $type->id,
                'weekday' => 6,
                'start_time' => '09:00',
                'duration_minutes' => 55,
                'capacity' => 20,
            ])
            ->assertSessionHasErrors('weekday');

        $this->assertDatabaseCount('class_groups', 0);
    }

    public function test_start_time_must_be_valid(): void
    {
        $type = ClassType::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.class-groups.store'), [
                'month' => $this->thisMonth(),
                'class_type_id' => $type->id,
                'weekday' => 2,
                'start_time' => '25:99',
                'duration_minutes' => 55,
                'capacity' => 20,
            ])
            ->assertSessionHasErrors('start_time');
    }

    public function test_admin_edits_a_class_without_changing_its_month(): void
    {
        $group = ClassGroup::factory()->create([
            'weekday' => 1,
            'start_time' => '08:00',
            'capacity' => 20,
            'active_from' => CarbonImmutable::today()->startOfMonth()->toDateString(),
        ]);
        $originalFrom = $group->active_from->toDateString();

        $this->actingAs($this->admin())
            ->put(route('admin.class-groups.update', $group), [
                'class_type_id' => $group->class_type_id,
                'trainer_id' => null,
                'weekday' => 4,
                'start_time' => '19:15',
                'duration_minutes' => 45,
                'capacity' => 12,
            ])
            ->assertRedirect();

        $group->refresh();
        $this->assertSame(4, $group->weekday->value);
        $this->assertSame('19:15', $group->startsAt());
        $this->assertSame('20:00', $group->endsAt());
        $this->assertSame(12, $group->capacity);
        $this->assertSame($originalFrom, $group->active_from->toDateString());
    }

    public function test_admin_deletes_a_class_from_the_pattern(): void
    {
        $group = ClassGroup::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.class-groups.destroy', $group))
            ->assertRedirect();

        $this->assertDatabaseMissing('class_groups', ['id' => $group->id]);
    }

    public function test_create_form_exposes_class_type_default_capacity(): void
    {
        ClassType::factory()->create(['default_capacity' => 30]);

        $this->actingAs($this->admin())
            ->get(route('admin.class-groups.create', ['month' => $this->thisMonth()]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ClassGroups/Create')
                ->where('classTypes.0.default_capacity', 30));
    }
}

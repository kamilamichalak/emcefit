<?php

namespace Tests\Feature\Admin;

use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelClassOccurrenceTest extends TestCase
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

    public function test_non_admin_cannot_cancel(): void
    {
        $occurrence = ClassSchedule::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.schedule.occurrences.cancel', $occurrence), ['reason' => 'x'])
            ->assertForbidden();
    }

    public function test_admin_cancels_a_single_occurrence_leaving_the_rest_untouched(): void
    {
        $group = ClassGroup::factory()->create();
        $target = ClassSchedule::factory()->for($group, 'classGroup')->create(['date' => '2026-09-06', 'start_time' => '10:00']);
        $sibling = ClassSchedule::factory()->for($group, 'classGroup')->create(['date' => '2026-09-13', 'start_time' => '10:00']);

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.occurrences.cancel', $target), ['reason' => 'Święto'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertSame(ClassOccurrenceStatus::Cancelled, $target->status);
        $this->assertSame('Święto', $target->cancellation_reason);

        // inne wystapienia tej samej grupy + wzorzec nietkniete
        $this->assertSame(ClassOccurrenceStatus::Planned, $sibling->refresh()->status);
        $this->assertDatabaseHas('class_groups', ['id' => $group->id]);
        $this->assertSame(2, ClassSchedule::count());
    }

    public function test_reason_is_required(): void
    {
        $occurrence = ClassSchedule::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.occurrences.cancel', $occurrence), ['reason' => '  '])
            ->assertSessionHasErrors('reason');

        $this->assertSame(ClassOccurrenceStatus::Planned, $occurrence->refresh()->status);
    }

    public function test_admin_restores_a_cancelled_occurrence(): void
    {
        $occurrence = ClassSchedule::factory()->cancelled('Awaria sali')->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.occurrences.restore', $occurrence))
            ->assertRedirect()
            ->assertSessionHas('success');

        $occurrence->refresh();
        $this->assertSame(ClassOccurrenceStatus::Planned, $occurrence->status);
        $this->assertNull($occurrence->cancellation_reason);
    }
}

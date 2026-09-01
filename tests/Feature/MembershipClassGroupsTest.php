<?php

namespace Tests\Feature;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Scheduling\Models\ClassGroup;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MembershipClassGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_class_group_id_column_is_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('memberships', 'class_group_id'));
        $this->assertTrue(Schema::hasTable('membership_class_groups'));
    }

    public function test_membership_has_many_class_groups(): void
    {
        $membership = Membership::factory()->create();
        $groups = ClassGroup::factory()->count(3)->create();

        $membership->classGroups()->attach($groups->pluck('id'));

        $this->assertCount(3, $membership->classGroups()->get());
        $this->assertTrue($groups->first()->memberships()->get()->contains($membership));
    }

    public function test_pair_cannot_be_attached_twice(): void
    {
        $membership = Membership::factory()->create();
        $group = ClassGroup::factory()->create();

        $membership->classGroups()->attach($group);

        $this->expectException(QueryException::class);
        $membership->classGroups()->attach($group);
    }

    public function test_deleting_a_class_group_removes_the_pivot_row(): void
    {
        $membership = Membership::factory()->create();
        $group = ClassGroup::factory()->create();
        $membership->classGroups()->attach($group);

        $group->delete();

        $this->assertDatabaseCount('membership_class_groups', 0);
        $this->assertModelExists($membership);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Domain\Scheduling\Models\ClassType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClassTypeManagementTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.class-types.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_class_types(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.class-types.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_the_list(): void
    {
        ClassType::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.class-types.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ClassTypes/Index')
                ->has('classTypes', 3));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Body Pump',
            'description' => 'Trening ze sztangą.',
            'required_equipment' => 'sztangi',
            'color' => '#E91E63',
            'icon' => 'Dumbbell',
            'default_capacity' => 20,
        ], $overrides);
    }

    public function test_admin_can_create_a_class_type(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.class-types.store'), $this->validPayload([
                'color' => '#3f51b5',
                'default_capacity' => 24,
            ]))
            ->assertRedirect(route('admin.class-types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('class_types', [
            'name' => 'Body Pump',
            'required_equipment' => 'sztangi',
            'color' => '#3F51B5',
            'icon' => 'Dumbbell',
            'default_capacity' => 24,
        ]);
    }

    public function test_icon_must_be_one_of_the_allowed_set(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.class-types.store'), $this->validPayload(['icon' => 'Skull']))
            ->assertSessionHasErrors('icon');

        $this->assertDatabaseCount('class_types', 0);
    }

    public function test_icon_is_stored_and_exposed_on_edit(): void
    {
        $classType = ClassType::factory()->create(['icon' => 'Dumbbell']);

        $this->actingAs($this->admin())
            ->put(route('admin.class-types.update', $classType), $this->validPayload([
                'name' => $classType->name,
                'icon' => 'HeartPulse',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('HeartPulse', $classType->refresh()->icon);

        $this->actingAs($this->admin())
            ->get(route('admin.class-types.edit', $classType))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('classType.icon', 'HeartPulse'));
    }

    public function test_color_must_be_a_hex_value(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.class-types.store'), $this->validPayload(['color' => 'czerwony']))
            ->assertSessionHasErrors('color');

        $this->assertDatabaseCount('class_types', 0);
    }

    public function test_default_capacity_falls_back_to_column_default(): void
    {
        $classType = ClassType::factory()->create();

        $this->assertSame(20, $classType->fresh()->default_capacity);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.class-types.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('class_types', 0);
    }

    public function test_name_must_be_unique_on_create(): void
    {
        ClassType::factory()->create(['name' => 'HIIT']);

        $this->actingAs($this->admin())
            ->post(route('admin.class-types.store'), ['name' => 'HIIT'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('class_types', 1);
    }

    public function test_admin_can_update_a_class_type(): void
    {
        $classType = ClassType::factory()->create(['name' => 'TBC', 'required_equipment' => null]);

        $this->actingAs($this->admin())
            ->put(route('admin.class-types.update', $classType), $this->validPayload([
                'name' => 'TBC Max',
                'description' => 'Mocniejsza wersja.',
                'required_equipment' => 'hantle',
                'color' => '#009688',
                'default_capacity' => 16,
            ]))
            ->assertRedirect(route('admin.class-types.index'));

        $classType->refresh();
        $this->assertSame('TBC Max', $classType->name);
        $this->assertSame('hantle', $classType->required_equipment);
        $this->assertSame('#009688', $classType->color);
        $this->assertSame(16, $classType->default_capacity);
    }

    public function test_update_allows_keeping_the_same_name(): void
    {
        $classType = ClassType::factory()->create(['name' => 'Fit Dance']);

        $this->actingAs($this->admin())
            ->put(route('admin.class-types.update', $classType), $this->validPayload(['name' => 'Fit Dance']))
            ->assertRedirect(route('admin.class-types.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_delete_a_class_type(): void
    {
        $classType = ClassType::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.class-types.destroy', $classType))
            ->assertRedirect(route('admin.class-types.index'));

        $this->assertDatabaseMissing('class_types', ['id' => $classType->id]);
    }
}

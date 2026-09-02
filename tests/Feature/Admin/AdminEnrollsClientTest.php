<?php

namespace Tests\Feature\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Scheduling\Actions\GenerateMonthlySchedule;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ClassTypeSeeder;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminEnrollsClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ClassTypeSeeder::class);
        $this->seed(MembershipTypeSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function client(): Client
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client;
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::today()->startOfMonth();
    }

    /** @return Collection<int, ClassGroup> */
    private function scheduledGroups(array $weekdays): Collection
    {
        $groups = collect($weekdays)->map(
            fn (int $wd) => ClassGroup::factory()->forMonth($this->month())->create(['weekday' => $wd]),
        );

        app(GenerateMonthlySchedule::class)->handle($this->month());
        EnrollmentWindow::factory()->forMonth($this->month())->open()->create();

        return $groups;
    }

    public function test_non_admin_cannot_open_admin_enrollment(): void
    {
        $client = $this->client();

        $this->get(route('admin.clients.enrollment.create', $client))->assertRedirect(route('login'));

        $this->actingAs($client->user)
            ->get(route('admin.clients.enrollment.create', $client))
            ->assertForbidden();
    }

    public function test_admin_sees_the_shared_enrollment_screen_in_client_context(): void
    {
        $this->scheduledGroups([1]);
        $client = $this->client();

        $this->actingAs($this->admin())
            ->get(route('admin.clients.enrollment.create', $client))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Enroll')
                ->where('ctx.admin_mode', true)
                ->where('ctx.client_name', $client->user->name)
                ->where('ctx.store_route', 'admin.clients.enrollment.store')
                ->where('ctx.route_params.client', $client->id));
    }

    public function test_admin_enrolls_the_client_and_records_who_registered_it(): void
    {
        $groups = $this->scheduledGroups([1, 3]); // 2x/tydz.
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route('admin.clients.enrollment.store', $client), [
                'month' => $this->month()->format('Y-m'),
                'class_group_ids' => $groups->pluck('id')->all(),
                'absences' => [],
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('success');

        $membership = $client->memberships()->sole();
        $this->assertSame($admin->id, $membership->registered_by_id);
        $this->assertSame('Zamknięty 2x/tydzień — miesięczny', $membership->membershipType->name);
        $this->assertSame('160.00', $membership->price_locked);
        $this->assertGreaterThan(0, $membership->reservations()->count());
    }

    public function test_self_service_enrollment_leaves_registered_by_null(): void
    {
        $groups = $this->scheduledGroups([1]);
        $client = $this->client();

        $this->actingAs($client->user)
            ->post(route('client.enrollment.store'), [
                'month' => $this->month()->format('Y-m'),
                'class_group_ids' => $groups->pluck('id')->all(),
                'absences' => [],
            ])
            ->assertRedirect();

        $this->assertNull($client->memberships()->sole()->registered_by_id);
    }

    public function test_admin_cannot_enroll_the_client_twice_for_the_same_month(): void
    {
        $groups = $this->scheduledGroups([1]);
        $admin = $this->admin();
        $client = $this->client();

        $payload = [
            'month' => $this->month()->format('Y-m'),
            'class_group_ids' => $groups->pluck('id')->all(),
            'absences' => [],
        ];

        $this->actingAs($admin)->post(route('admin.clients.enrollment.store', $client), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.clients.enrollment.store', $client), $payload)
            ->assertSessionHasErrors('class_group_ids');

        $this->assertCount(1, $client->memberships);
    }
}

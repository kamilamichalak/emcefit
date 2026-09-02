<?php

namespace Tests\Feature\Admin;

use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EnrollmentWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        // miesiąc testowy musi być "bieżący" — auto-zamknięcie minionych (Prompt 10h)
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
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

    public function test_non_admin_cannot_toggle_enrollment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.schedule.enrollment'), ['month' => '2026-06', 'open' => true])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_windows', 0);
    }

    public function test_admin_opens_enrollment_for_a_month(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.enrollment'), ['month' => '2026-06', 'open' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        $window = EnrollmentWindow::sole();
        $this->assertSame(2026, $window->year);
        $this->assertSame(6, $window->month);
        $this->assertTrue($window->open);
        $this->assertNotNull($window->opened_at);
        $this->assertTrue(EnrollmentWindow::isOpenFor($this->month()));
    }

    public function test_admin_closes_enrollment_without_duplicating_the_row(): void
    {
        EnrollmentWindow::factory()->forMonth($this->month())->open()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.enrollment'), ['month' => '2026-06', 'open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('enrollment_windows', 1);
        $this->assertFalse(EnrollmentWindow::sole()->open);
        $this->assertFalse(EnrollmentWindow::isOpenFor($this->month()));
    }

    public function test_generating_the_schedule_does_not_open_enrollment(): void
    {
        $month = $this->month();
        ClassGroup::factory()->forMonth($month)->create(['weekday' => 1, 'start_time' => '18:00']);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.generate'), ['month' => $month->format('Y-m')])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('enrollment_windows', 0);
        $this->assertFalse(EnrollmentWindow::isOpenFor($month));
    }

    public function test_is_open_for_defaults_to_false(): void
    {
        $this->assertFalse(EnrollmentWindow::isOpenFor($this->month()));
    }

    public function test_ended_month_is_closed_even_when_the_flag_is_open(): void
    {
        // "teraz" = 2026-06-15; maj 2026 już się zakończył (Prompt 10h)
        $may = CarbonImmutable::parse('2026-05-01');
        EnrollmentWindow::factory()->forMonth($may)->open()->create();
        EnrollmentWindow::factory()->forMonth($this->month())->open()->create();

        $this->assertFalse(EnrollmentWindow::isOpenFor($may), 'miniony miesiąc = zamknięty mimo flagi');
        $this->assertTrue(EnrollmentWindow::isOpenFor($this->month()), 'bieżący miesiąc z flagą = otwarty');
    }

    public function test_schedule_index_exposes_enrollment_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.schedule.index', ['month' => '2026-06']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Schedule/Index')
                ->where('enrollmentOpen', false));

        EnrollmentWindow::factory()->forMonth($this->month())->open()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.schedule.index', ['month' => '2026-06']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('enrollmentOpen', true));
    }
}

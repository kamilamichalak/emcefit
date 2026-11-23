<?php

namespace Tests\Feature;

use App\Domain\Memberships\Models\Membership;
use App\Support\PaymentDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_is_28th_of_previous_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00'));
        $m = Membership::factory()->make(['start_date' => '2026-12-01']);

        $this->assertSame('2026-11-28', PaymentDeadline::for($m)->toDateString());
    }

    public function test_after_28th_falls_back_to_three_days(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-29 10:00'));
        $m = Membership::factory()->make(['start_date' => '2026-12-01']);

        $this->assertSame('2026-12-02', PaymentDeadline::for($m)->toDateString());
    }

    public function test_on_the_28th_itself_deadline_is_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-28 21:00'));
        $m = Membership::factory()->make(['start_date' => '2026-12-01']);

        $this->assertSame('2026-11-28', PaymentDeadline::for($m)->toDateString());
    }
}

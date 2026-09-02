<?php

namespace Tests\Feature\Client;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Reservations\Actions\PromoteFromWaitlist;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteFromWaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function client(): User
    {
        $client = Client::factory()->create();
        $client->user->assignRole('client');

        return $client->user;
    }

    private function occurrence(int $capacity, string $time = '18:00'): ClassSchedule
    {
        $group = ClassGroup::factory()->create(['capacity' => $capacity, 'start_time' => $time]);

        return ClassSchedule::factory()->create([
            'class_group_id' => $group->id,
            'date' => '2026-06-16',
            'start_time' => $time,
        ]);
    }

    private function reservationFor(User $user, ClassSchedule $occurrence, ReservationStatus $status, ?string $confirmedAt): Reservation
    {
        $membership = Membership::factory()->create([
            'client_id' => $user->client->id,
            'start_date' => '2026-06-01',
        ]);

        return Reservation::factory()->create([
            'client_id' => $user->client->id,
            'membership_id' => $membership->id,
            'class_schedule_id' => $occurrence->id,
            'status' => $status,
            'confirmed_at' => $confirmedAt ? CarbonImmutable::parse($confirmedAt) : null,
        ]);
    }

    public function test_cancelling_a_seat_promotes_the_earliest_paid_waitlister(): void
    {
        $occurrence = $this->occurrence(capacity: 1);

        $owner = $this->client();
        $ownerReservation = $this->reservationFor($owner, $occurrence, ReservationStatus::Confirmed, '2026-06-05');

        $early = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-08');
        $late = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-11');

        $this->actingAs($owner)
            ->patch(route('client.reservations.cancel', $ownerReservation))
            ->assertRedirect();

        $this->assertSame(ReservationStatus::Cancelled, $ownerReservation->refresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $early->refresh()->status);
        $this->assertSame(ReservationStatus::Waitlist, $late->refresh()->status);
        // data potwierdzenia promowanej osoby zostaje bez zmian
        $this->assertSame('2026-06-08', $early->confirmed_at->toDateString());
    }

    public function test_no_waitlist_means_nothing_to_promote(): void
    {
        $occurrence = $this->occurrence(capacity: 5);
        $result = app(PromoteFromWaitlist::class)->handle($occurrence);

        $this->assertNull($result);
    }

    public function test_promotion_is_skipped_when_the_occurrence_is_still_full(): void
    {
        $occurrence = $this->occurrence(capacity: 1);
        $this->reservationFor($this->client(), $occurrence, ReservationStatus::Confirmed, '2026-06-05');
        $waiting = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-08');

        $result = app(PromoteFromWaitlist::class)->handle($occurrence);

        $this->assertNull($result);
        $this->assertSame(ReservationStatus::Waitlist, $waiting->refresh()->status);
    }

    public function test_equal_confirmation_dates_promote_the_earlier_reservation(): void
    {
        $occurrence = $this->occurrence(capacity: 1);
        $first = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-08 10:00:00');
        $second = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-08 10:00:00');

        app(PromoteFromWaitlist::class)->handle($occurrence);

        $this->assertSame(ReservationStatus::Confirmed, $first->refresh()->status);
        $this->assertSame(ReservationStatus::Waitlist, $second->refresh()->status);
    }

    public function test_only_one_person_is_promoted_per_freed_seat(): void
    {
        $occurrence = $this->occurrence(capacity: 2);
        $this->reservationFor($this->client(), $occurrence, ReservationStatus::Confirmed, '2026-06-05');
        $w1 = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-08');
        $w2 = $this->reservationFor($this->client(), $occurrence, ReservationStatus::Waitlist, '2026-06-09');

        app(PromoteFromWaitlist::class)->handle($occurrence);

        $this->assertSame(ReservationStatus::Confirmed, $w1->refresh()->status);
        $this->assertSame(ReservationStatus::Waitlist, $w2->refresh()->status);
    }
}

<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SubmitEnrollment
{
    /**
     * Zgłoszenie chęci udziału na miesiąc kalendarzowy (abonament zamknięty):
     * membership + wpisy membership_class_groups + rezerwacja na każde wystąpienie
     * wybranych zajęć. Wystąpienie odwołane przez klub LUB oznaczone przez klienta
     * jako nieobecność => rezerwacja `odwolana` + `makeup_credit`.
     *
     * Płatności NIE dotykamy — admin ręcznie księguje wpłatę (Faza 1).
     *
     * @param  list<int>  $classGroupIds
     * @param  list<int>  $absentScheduleIds  wystąpienia (class_schedule.id) z planowaną nieobecnością
     */
    public function handle(
        Client $client,
        MembershipType $type,
        CarbonImmutable $month,
        array $classGroupIds,
        array $absentScheduleIds,
    ): Membership {
        return DB::transaction(function () use ($client, $type, $month, $classGroupIds, $absentScheduleIds): Membership {
            $membership = $client->memberships()->create([
                'membership_type_id' => $type->id,
                'start_date' => $month->startOfMonth()->toDateString(),
                'end_date' => $month->endOfMonth()->toDateString(),
            ]);

            $membership->classGroups()->attach($classGroupIds);

            $occurrences = ClassSchedule::query()
                ->whereIn('class_group_id', $classGroupIds)
                ->whereBetween('date', [
                    $month->startOfMonth()->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ])
                ->get();

            $absent = array_flip($absentScheduleIds);
            $now = now();

            foreach ($occurrences as $occurrence) {
                $skipped = $occurrence->status === ClassOccurrenceStatus::Cancelled
                    || isset($absent[$occurrence->id]);

                $reservation = Reservation::create([
                    'client_id' => $client->id,
                    'class_schedule_id' => $occurrence->id,
                    'membership_id' => $membership->id,
                    'status' => $skipped ? ReservationStatus::Cancelled : ReservationStatus::PendingPayment,
                    'reported_at' => $now,
                ]);

                if ($skipped) {
                    MakeupCredit::create([
                        'client_id' => $client->id,
                        'source_reservation_id' => $reservation->id,
                        'expires_end_of_month' => true,
                        'used' => false,
                    ]);
                }
            }

            return $membership->load(['membershipType', 'classGroups.classType', 'reservations']);
        });
    }
}

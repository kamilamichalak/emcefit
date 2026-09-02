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
     * jako nieobecność => rezerwacja `zwolnione` + `makeup_credit`.
     *
     * Płatności NIE dotykamy — admin ręcznie księguje wpłatę (Faza 1).
     *
     * Dla krótszego wariantu (Prompt 10e) `firstEntryDate`/`endDate` zawężają okres
     * karnetu do [pierwsze "będę" .. ostatnie "będę"]; rezerwacje powstają tylko dla
     * wystąpień w tym oknie (pominięte skrajne tygodnie nie dają ani rezerwacji, ani
     * makeup_credit). Dla wariantu miesięcznego oba są null → całe okno miesiąca.
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
        ?CarbonImmutable $firstEntryDate = null,
        ?CarbonImmutable $endDate = null,
    ): Membership {
        return DB::transaction(function () use ($client, $type, $month, $classGroupIds, $absentScheduleIds, $firstEntryDate, $endDate): Membership {
            $windowStart = ($firstEntryDate ?? $month->startOfMonth())->toDateString();
            $windowEnd = ($endDate ?? $month->endOfMonth())->toDateString();

            $membership = $client->memberships()->create([
                'membership_type_id' => $type->id,
                // migawka ceny na moment zapisu (Prompt 11a) — uwzględnia krótszy wariant z 10e
                'price_locked' => $type->price,
                'start_date' => $month->startOfMonth()->toDateString(),
                'first_entry_date' => $firstEntryDate?->toDateString(),
                'end_date' => $windowEnd,
            ]);

            $membership->classGroups()->attach($classGroupIds);

            $occurrences = ClassSchedule::query()
                ->whereIn('class_group_id', $classGroupIds)
                ->whereBetween('date', [$windowStart, $windowEnd])
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
                    'status' => $skipped ? ReservationStatus::Released : ReservationStatus::PendingPayment,
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

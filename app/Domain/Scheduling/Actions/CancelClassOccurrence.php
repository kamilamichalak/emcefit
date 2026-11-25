<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Reservations\Enums\ReservationKind;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\MakeupCredit;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Mail\ClassCancelledMail;
use App\Support\ClientMailer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Odwołanie pojedynczego wystąpienia zajęć przez studio (i jego przywrócenie).
 * Nie rusza wzorca (class_groups) ani innych wystąpień.
 *
 * Skutki dla zapisanych (ustalone z Kamilą 17.11.2026):
 * - zapis stały → odrobienie (do końca miesiąca zajęć), zapis zostaje — przy przywróceniu
 *   zajęć wraca, a niewykorzystane odrobienie znika;
 * - zajęcia odrabiane → odrobienie wraca, miejsce zwolnione;
 * - karnet otwarty → wejście wraca, miejsce zwolnione;
 * - lista oczekujących → zdjęta;
 * - mail do klientek (zapis stały, odrabianie, karnet otwarty), jeśli go nie wyłączyły.
 */
final class CancelClassOccurrence
{
    public function cancel(ClassSchedule $occurrence, string $reason, bool $notify = true): ClassSchedule
    {
        $affected = DB::transaction(function () use ($occurrence, $reason) {
            $occurrence->update([
                'status' => ClassOccurrenceStatus::Cancelled,
                'cancellation_reason' => $reason,
            ]);

            return $this->compensate($occurrence);
        });

        if ($notify && $occurrence->date->endOfDay()->isFuture()) {
            foreach ($affected as $client) {
                ClientMailer::send($client, new ClassCancelledMail($client, $occurrence->fresh(['classGroup.classType'])), 'class_cancelled');
            }
        }

        return $occurrence;
    }

    /**
     * Rekompensata dla zapisanych na odwołane zajęcia. Idempotentna — drugi raz nic nie zmienia.
     *
     * @return list<Client> klientki do powiadomienia
     */
    public function compensate(ClassSchedule $occurrence): array
    {
        $occurrence->loadMissing('reservations.client.user', 'reservations.makeupCredit');
        // odrobienie ważne do końca miesiąca odwołanych zajęć (a nie miesiąca, w którym odwołano)
        $creditCreatedAt = CarbonImmutable::now()->max(CarbonImmutable::parse($occurrence->date->toDateString())->startOfMonth());
        $notify = [];

        foreach ($occurrence->reservations as $reservation) {
            $active = in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::PendingPayment], true);

            if ($reservation->status === ReservationStatus::Waitlist) {
                $reservation->update(['status' => ReservationStatus::Released, 'late_release' => false]);

                continue;
            }

            if (! $active) {
                continue;
            }

            if ($reservation->kind === ReservationKind::Fixed) {
                if ($reservation->makeupCredit === null) {
                    $credit = new MakeupCredit([
                        'client_id' => $reservation->client_id,
                        'source_reservation_id' => $reservation->id,
                        'expires_end_of_month' => true,
                        'used' => false,
                    ]);
                    $credit->created_at = $creditCreatedAt;
                    $credit->save();
                    $notify[] = $reservation->client;
                }

                continue;
            }

            if ($reservation->kind === ReservationKind::Makeup && $reservation->makeup_credit_id) {
                MakeupCredit::whereKey($reservation->makeup_credit_id)->update(['used' => false]);
            }

            $reservation->update([
                'status' => ReservationStatus::Released,
                'late_release' => false,
                'makeup_credit_id' => null,
            ]);
            $notify[] = $reservation->client;
        }

        return $notify;
    }

    public function restore(ClassSchedule $occurrence): ClassSchedule
    {
        DB::transaction(function () use ($occurrence) {
            $occurrence->update([
                'status' => ClassOccurrenceStatus::Planned,
                'cancellation_reason' => null,
            ]);

            // zapisy stałe wracają; niewykorzystane odrobienia za odwołanie znikają
            // (zwolnienia klientek mają status „zwolnione”, więc ich odrobień to nie dotyczy)
            MakeupCredit::query()
                ->where('used', false)
                ->whereIn('source_reservation_id', Reservation::query()
                    ->where('class_schedule_id', $occurrence->id)
                    ->where('kind', ReservationKind::Fixed)
                    ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::PendingPayment])
                    ->select('id'))
                ->delete();
        });

        return $occurrence;
    }
}

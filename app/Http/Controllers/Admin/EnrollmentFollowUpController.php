<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Actions\AssignMembership;
use App\Domain\Memberships\Actions\ResolveClosedMembershipVariant;
use App\Domain\Memberships\Data\MembershipData;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Actions\RegisterPayment;
use App\Domain\Payments\Data\PaymentData;
use App\Domain\Reservations\Actions\ConfirmReservationsForPayment;
use App\Domain\Reservations\Actions\SubmitEnrollment;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Http\Controllers\Controller;
use App\Mail\EnrollmentSubmittedMail;
use App\Mail\PaymentSettledMail;
use App\Support\ClientMailer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Szybkie akcje z pulpitu Majki przy domykaniu zapisów: zapis „jak w poprzednim
 * miesiącu” jednym kliknięciem i zaksięgowanie pełnej wpłaty jednym kliknięciem.
 */
class EnrollmentFollowUpController extends Controller
{
    public function repeat(
        Request $request,
        Client $client,
        ResolveClosedMembershipVariant $resolveVariant,
        SubmitEnrollment $submitEnrollment,
        AssignMembership $assign,
    ): RedirectResponse {
        $month = CarbonImmutable::parse($request->validate(['month' => ['required', 'date_format:Y-m']])['month'].'-01');
        $name = $client->user->name;

        if ($client->memberships()->whereDate('start_date', $month->toDateString())->exists()) {
            return back()->with('error', "{$name} ma już karnet na ten miesiąc.");
        }

        $previous = $client->memberships()
            ->whereDate('start_date', $month->subMonthNoOverflow()->toDateString())
            ->with(['membershipType', 'classGroups'])
            ->first();

        if ($previous === null) {
            return back()->with('error', "{$name} nie miała karnetu w poprzednim miesiącu — zapisz ją ręcznie.");
        }

        if ($previous->membershipType->mode === MembershipMode::Open) {
            $membership = $assign->handle($client, new MembershipData($previous->membership_type_id, $month->toDateString(), null, null, null));
        } else {
            $active = ClassGroup::query()->activeForMonth($month)->get();
            $groupIds = [];

            foreach ($previous->classGroups as $old) {
                $match = $active->first(fn (ClassGroup $new) => $new->weekday === $old->weekday
                    && $new->startsAt() === $old->startsAt()
                    && $new->class_type_id === $old->class_type_id);

                if ($match === null) {
                    return back()->with('error', "Zajęcia {$name} z poprzedniego miesiąca nie istnieją już w grafiku — zapisz ją ręcznie („Zapisz inaczej”).");
                }

                $groupIds[] = $match->id;
            }

            $variant = $resolveVariant->handle($groupIds, $month, []);

            if ($variant->type === null) {
                return back()->with('error', "Cennik nie ma karnetu na tyle zajęć — zapisz {$name} ręcznie.");
            }

            $membership = $submitEnrollment->handle($client, $variant->type, $month, $groupIds, [], $variant->firstEntryDate, $variant->endDate, $request->user()->id);
        }

        ClientMailer::send($client, new EnrollmentSubmittedMail($membership), 'enrollment_submitted');

        return back()->with('success', "Zapisano: {$name} — {$membership->membershipType->name}.");
    }

    public function settle(
        Request $request,
        Membership $membership,
        RegisterPayment $registerPayment,
        ConfirmReservationsForPayment $confirmReservations,
    ): RedirectResponse {
        $membership->load(['client.user', 'payments']);
        $due = $membership->amountDue();
        $name = $membership->client->user->name;

        if ($due <= 0) {
            return back()->with('error', "Karnet {$name} jest już opłacony.");
        }

        $payment = $registerPayment->handle($membership, new PaymentData(
            $due,
            null,
            null,
            true,
            'zajęcia fitness, '.$name.', '.$membership->start_date->translatedFormat('F Y'),
        ));

        $result = $confirmReservations->handle($payment);
        ClientMailer::send($membership->client, new PaymentSettledMail($membership->fresh(), $result['waitlisted']), 'payment_settled');

        return back()->with('success', "Zaksięgowano {$due} zł — {$name}.");
    }
}

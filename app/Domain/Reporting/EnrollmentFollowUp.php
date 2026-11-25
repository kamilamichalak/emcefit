<?php

namespace App\Domain\Reporting;

use App\Domain\Clients\Actions\SendClientInvitation;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Enums\MembershipMode;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Support\PolishMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * „Co zostało do zrobienia” przy zapisach na kolejny miesiąc — dla pulpitu Majki:
 * kto się jeszcze nie zapisał (i dlaczego) oraz kto nie zapłacił.
 */
final class EnrollmentFollowUp
{
    /**
     * @return array<string, mixed>
     */
    public function forMonth(CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $previous = $month->subMonthNoOverflow();

        $clients = Client::query()
            ->with(['user:id,name,email', 'memberships.membershipType', 'memberships.classGroups.classType', 'memberships.payments'])
            ->get()
            // konta techniczne (np. testowe) nie mają karnetów i zaproszeń — pomijamy
            ->filter(fn (Client $c) => $c->memberships->isNotEmpty() || $c->invitation_sent_at !== null)
            ->reject(fn (Client $c) => str_contains(mb_strtolower($c->user->name), 'konto testowe'));

        $current = fn (Client $c) => $c->memberships->first(fn (Membership $m) => $m->start_date->equalTo($month));
        $last = fn (Client $c) => $c->memberships->first(fn (Membership $m) => $m->start_date->equalTo($previous));

        $lastSeen = DB::table('sessions')->whereNotNull('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last')->groupBy('user_id')->pluck('last', 'user_id');

        $notEnrolled = $clients->reject(fn (Client $c) => $current($c) !== null)
            // ktoś bez karnetu w poprzednim miesiącu i bez konta — to raczej nie „do pogonienia”
            ->filter(fn (Client $c) => $last($c) !== null || $c->invitation_used_at !== null)
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->user->name,
                'previous' => $this->planLabel($last($c)),
                'can_repeat' => $last($c) !== null,
                'no_email' => SendClientInvitation::isPlaceholderEmail($c->user->email),
                'activated' => $c->invitation_used_at !== null,
                'invited_at' => $c->invitation_sent_at?->copy()->setTimezone('Europe/Warsaw')->format('j.m'),
                'last_seen' => isset($lastSeen[$c->user_id])
                    ? CarbonImmutable::createFromTimestamp((int) $lastSeen[$c->user_id])->setTimezone('Europe/Warsaw')->format('j.m H:i')
                    : null,
            ])
            ->sortBy('name', SORT_LOCALE_STRING)
            ->values();

        $unpaid = $clients->map(fn (Client $c) => [$c, $current($c)])
            ->filter(fn (array $pair) => $pair[1] !== null && $pair[1]->amountDue() > 0)
            ->map(fn (array $pair) => [
                'id' => $pair[0]->id,
                'membership_id' => $pair[1]->id,
                'name' => $pair[0]->user->name,
                'type_name' => $pair[1]->membershipType->name,
                'amount_due' => $pair[1]->amountDue(),
                'partly_paid' => $pair[1]->isPaid(),
                'enrolled_at' => $pair[1]->created_at->copy()->setTimezone('Europe/Warsaw')->format('j.m'),
            ])
            ->sortBy('name', SORT_LOCALE_STRING)
            ->values();

        $enrolled = $clients->filter(fn (Client $c) => $current($c) !== null)->count();

        return [
            'month' => ['value' => $month->format('Y-m'), 'label' => $month->translatedFormat('F'), 'previous_in' => PolishMonth::in($previous)],
            'enrolled' => $enrolled,
            'expected' => $enrolled + $notEnrolled->count(),
            'paid' => $enrolled - $unpaid->count(),
            'unpaidTotal' => round((float) $unpaid->sum('amount_due'), 2),
            'noEmail' => $notEnrolled->where('no_email', true)->values(),
            'notActivated' => $notEnrolled->where('no_email', false)->where('activated', false)->values(),
            'activeNotEnrolled' => $notEnrolled->where('activated', true)->values(),
            'unpaid' => $unpaid,
        ];
    }

    private function planLabel(?Membership $membership): ?string
    {
        if ($membership === null) {
            return null;
        }

        if ($membership->membershipType->mode === MembershipMode::Open) {
            return 'karnet otwarty';
        }

        return $membership->classGroups
            ->sortBy(fn (ClassGroup $g) => sprintf('%d %s', $g->weekday->value, $g->start_time))
            ->map(fn (ClassGroup $g) => $g->weekday->shortLabel().' '.$g->startsAt().' '.$g->classType->name)
            ->implode(', ');
    }
}

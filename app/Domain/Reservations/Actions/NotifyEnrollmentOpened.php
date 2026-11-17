<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Clients\Actions\SendClientInvitation;
use App\Domain\Clients\Models\Client;
use App\Domain\Reservations\Models\EnrollmentWindow;
use App\Mail\EnrollmentOpenedMail;
use App\Support\ClientMailer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Mail „Zapisy na [miesiąc] są otwarte” do klientek z aktywnym kontem i prawdziwym adresem.
 * Wysyłany świadomie przyciskiem w panelu studia, najwyżej raz na miesiąc.
 */
final class NotifyEnrollmentOpened
{
    /** @return Collection<int, Client> */
    public function recipients(): Collection
    {
        return Client::query()
            ->whereNotNull('invitation_used_at')
            ->with('user:id,name,email')
            ->get()
            ->reject(fn (Client $client) => SendClientInvitation::isPlaceholderEmail($client->user->email))
            ->filter(fn (Client $client) => $client->wantsMail('enrollment_opened'))
            ->values();
    }

    /**
     * @return int liczba wysłanych maili
     *
     * @throws DomainException gdy zapisy nie są otwarte albo powiadomienie już poszło
     */
    public function handle(CarbonImmutable $month): int
    {
        $window = EnrollmentWindow::query()->where('year', $month->year)->where('month', $month->month)->first();

        if ($window === null || ! $window->open) {
            throw new DomainException('Najpierw otwórz zapisy na ten miesiąc.');
        }

        if ($window->notified_at !== null) {
            throw new DomainException('Powiadomienie o tych zapisach zostało już wysłane.');
        }

        // znacznik przed wysyłką — podwójne kliknięcie nie wyśle drugiej serii
        $window->update(['notified_at' => now()]);

        $sent = $this->recipients()
            ->filter(fn (Client $client) => ClientMailer::send($client, new EnrollmentOpenedMail($client->user->name, $month), 'enrollment_opened'))
            ->count();

        $window->update(['notified_count' => $sent]);

        return $sent;
    }
}

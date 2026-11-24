<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Data\ClientData;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\EmailSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wpis z formularza /dane → konto + zaproszenie (Kamila 24.11.2026).
 *
 * - adres jest już na koncie → nic nie robimy;
 * - dokładnie jedno konto z listy Majki o tym imieniu i nazwisku, bez prawdziwego maila → przypisujemy adres;
 * - nikogo podobnego w systemie → zakładamy nowe konto;
 * - wątpliwości (kilka kont, podobne nazwisko — literówka, samo nazwisko) → zostawiamy do decyzji Kamili.
 * Potem wysyłamy zaproszenie; gdy się nie uda (np. dzienny limit maili), zostaje do ponownej wysyłki.
 */
final class ProcessEmailSubmission
{
    /** Ochrona publicznego formularza: tyle nowych kont dziennie zakładamy sami, resztę zatwierdza studio. */
    public const MAX_NEW_ACCOUNTS_PER_DAY = 10;

    public function __construct(
        private CreateClient $createClient,
        private SendClientInvitation $sendInvitation,
    ) {}

    /** @return string co się stało: existing|assigned|created|manual */
    public function handle(EmailSubmission $submission): string
    {
        $email = strtolower(trim($submission->email));
        $name = trim($submission->full_name);

        if (stripos($name, 'testtest') !== false || User::where('email', $email)->exists()) {
            return 'existing';
        }

        $clients = Client::with('user')->get();
        $placeholders = $clients->filter(fn (Client $c) => SendClientInvitation::isPlaceholderEmail($c->user->email));
        $target = self::normalize($name);
        $words = explode(' ', $target);

        $exact = $placeholders->filter(fn (Client $c) => self::normalize($c->user->name) === $target);

        if ($exact->count() === 1) {
            $client = $exact->first();
            $client->user->update(['email' => $email]);
            $this->invite($client);

            return 'assigned';
        }

        if ($exact->isEmpty() && count($words) >= 2 && ! $this->hasLookalike($clients, $words)
            && Client::whereDate('created_at', today())->count() < self::MAX_NEW_ACCOUNTS_PER_DAY) {
            $client = $this->createClient->handle(new ClientData($name, $email, null, null));
            $this->invite($client);

            return 'created';
        }

        Log::info('Formularz /dane: wpis do ręcznej decyzji', ['submission_id' => $submission->id]);

        return 'manual';
    }

    /**
     * Ktoś w systemie ma to samo albo bardzo podobne nazwisko (literówka, inna forma imienia) —
     * lepiej, żeby zdecydował człowiek, niż założyć klientce drugie konto.
     *
     * @param  Collection<int, Client>  $clients
     * @param  list<string>  $words
     */
    private function hasLookalike(Collection $clients, array $words): bool
    {
        $surname = end($words);

        return $clients->contains(function (Client $c) use ($surname, $words) {
            $other = explode(' ', self::normalize($c->user->name));

            foreach ($other as $part) {
                if (strlen($part) >= 4 && levenshtein($part, $surname) <= 2) {
                    return true;
                }
            }

            return count($other) >= 2 && $other[0] === $words[0] && levenshtein(end($other), $surname) <= 3;
        });
    }

    private function invite(Client $client): void
    {
        try {
            $this->sendInvitation->handle($client->fresh('user'));
        } catch (Throwable $e) {
            Log::warning('Formularz /dane: zaproszenie nie wyszło — do ponownej wysyłki', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function normalize(string $name): string
    {
        $name = str_replace(['ł', 'Ł', 'е', '-'], ['l', 'L', 'e', ' '], $name);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;

        return trim(preg_replace('/[^a-z]+/', ' ', strtolower($ascii)));
    }
}

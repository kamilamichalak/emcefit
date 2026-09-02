<?php

namespace App\Console\Commands;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Models\MembershipType;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Scheduling\Enums\Weekday;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lokalne narzędzie developerskie — wczytuje fikcyjnych klientów (email @test.pl)
 * z pliku JSON. NIE jest podpięte pod DatabaseSeeder, żeby nie odpalało się
 * automatycznie. Parą jest test-data:clear.
 */
class TestDataSeed extends Command
{
    protected $signature = 'test-data:seed {--file=test-data/testowi_klienci.json : ścieżka względem storage/app}';

    protected $description = 'Wczytuje lokalne dane testowe klientów (@test.pl) z pliku JSON — tylko dev.';

    /** @var array<string, Weekday> */
    private array $dayMap = [
        'poniedzialek' => Weekday::Monday,
        'poniedziałek' => Weekday::Monday,
        'wtorek' => Weekday::Tuesday,
        'sroda' => Weekday::Wednesday,
        'środa' => Weekday::Wednesday,
        'czwartek' => Weekday::Thursday,
        'piatek' => Weekday::Friday,
        'piątek' => Weekday::Friday,
        'sobota' => Weekday::Saturday,
        'niedziela' => Weekday::Sunday,
    ];

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('test-data:seed jest niedozwolone w środowisku produkcyjnym.');

            return self::FAILURE;
        }

        $path = storage_path('app/'.$this->option('file'));
        if (! is_file($path)) {
            $this->error("Nie znaleziono pliku: {$path}");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $month = CarbonImmutable::parse(($data['miesiac_docelowy'] ?? '2026-09').'-01')->startOfMonth();

        // Grafik aktywny w docelowym miesiącu, indeksowany po (weekday|H:i).
        $groups = ClassGroup::query()->activeForMonth($month)->get()
            ->keyBy(fn (ClassGroup $g): string => $this->slotKey($g->weekday, $g->start_time));

        // Wygenerowane wystąpienia w tym miesiącu, pogrupowane po class_group_id.
        $occurrences = ClassSchedule::query()
            ->whereIn('class_group_id', $groups->pluck('id'))
            ->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->get()
            ->groupBy('class_group_id');

        $stats = ['clients' => 0, 'skippedClients' => 0, 'memberships' => 0, 'payments' => 0, 'reservations' => 0, 'skippedSlots' => 0];

        DB::transaction(function () use ($data, $month, $groups, $occurrences, &$stats): void {
            foreach ($data['klienci'] ?? [] as $row) {
                $email = strtolower(trim((string) $row['email']));

                if (! str_ends_with($email, '@test.pl')) {
                    $this->warn("· {$email} — email nie kończy się na @test.pl, pomijam");
                    $stats['skippedClients']++;

                    continue;
                }

                if (User::where('email', $email)->exists()) {
                    $this->line("· {$email} — już istnieje, pomijam");
                    $stats['skippedClients']++;

                    continue;
                }

                // Dopasowanie zajęć po (dzień, godzina) — brak dopasowania = pomiń pojedynczy wpis.
                $matchedGroups = [];
                foreach ($row['membership']['zajecia'] ?? [] as $slot) {
                    $weekday = $this->dayMap[Str::lower(trim((string) $slot['dzien_tygodnia']))] ?? null;
                    if ($weekday === null) {
                        $this->warn("  {$email}: nieznany dzień tygodnia ({$slot['dzien_tygodnia']}), pomijam wpis");
                        $stats['skippedSlots']++;

                        continue;
                    }

                    $key = $this->slotKey($weekday, (string) $slot['godzina']);
                    $group = $groups->get($key);
                    if ($group === null) {
                        $this->warn("  {$email}: brak w grafiku {$slot['dzien_tygodnia']} {$slot['godzina']}, pomijam wpis");
                        $stats['skippedSlots']++;

                        continue;
                    }

                    $matchedGroups[$group->id] = $group;
                }

                if ($matchedGroups === []) {
                    $this->warn("· {$email} — żadne zajęcie nie pasuje do grafiku, pomijam klienta");
                    $stats['skippedClients']++;

                    continue;
                }

                $sessions = count($matchedGroups);
                $type = MembershipType::monthlyClosedForSessions($sessions)
                    ?? MembershipType::monthlyClosedForSessions((int) ($row['membership']['sesje_w_tygodniu'] ?? 0));

                if ($type === null) {
                    $this->warn("· {$email} — brak wariantu cennika na {$sessions}x/tydz., pomijam klienta");
                    $stats['skippedClients']++;

                    continue;
                }

                $now = CarbonImmutable::now();

                $user = User::create([
                    'name' => trim(($row['imie'] ?? '').' '.($row['nazwisko'] ?? '')),
                    'email' => $email,
                    'password' => 'password',
                ]);
                $user->assignRole('client');

                $client = Client::create([
                    'user_id' => $user->id,
                    'phone' => $row['telefon'] ?? null,
                    'status' => ClientStatus::Active,
                    'join_date' => $month->toDateString(),
                    'terms_accepted_at' => $now,
                    'health_declaration_at' => $now,
                    'invitation_used_at' => $now,
                ]);
                $stats['clients']++;

                $settled = ($row['payment']['status'] ?? 'oczekuje') === 'zaksiegowana';

                $membership = $client->memberships()->create([
                    'membership_type_id' => $type->id,
                    'price_locked' => $row['membership']['cena_ustalona'] ?? $type->price,
                    'start_date' => $month->toDateString(),
                    'end_date' => $month->endOfMonth()->toDateString(),
                ]);
                $membership->classGroups()->attach(array_keys($matchedGroups));
                $stats['memberships']++;

                $client->payments()->create([
                    'membership_id' => $membership->id,
                    'amount' => $row['payment']['kwota'] ?? $type->price,
                    'reported_date' => $month->toDateString(),
                    'settled_date' => $settled ? $month->toDateString() : null,
                    'status' => $settled ? PaymentStatus::Settled : PaymentStatus::Pending,
                    'transfer_title' => 'zajęcia fitness, '.$user->name.', '.$month->translatedFormat('F Y'),
                ]);
                $stats['payments']++;

                $reservationStatus = $settled ? ReservationStatus::Confirmed : ReservationStatus::PendingPayment;
                $confirmedAt = $settled ? $month->startOfDay() : null;

                foreach ($matchedGroups as $groupId => $group) {
                    foreach ($occurrences->get($groupId, collect()) as $occurrence) {
                        Reservation::create([
                            'client_id' => $client->id,
                            'class_schedule_id' => $occurrence->id,
                            'membership_id' => $membership->id,
                            'status' => $reservationStatus,
                            'reported_at' => $now,
                            'confirmed_at' => $confirmedAt,
                        ]);
                        $stats['reservations']++;
                    }
                }
            }
        });

        $this->newLine();
        $this->info('Import zakończony:');
        $this->table(
            ['klienci', 'karnety', 'płatności', 'rezerwacje', 'pominięci klienci', 'pominięte wpisy zajęć'],
            [[$stats['clients'], $stats['memberships'], $stats['payments'], $stats['reservations'], $stats['skippedClients'], $stats['skippedSlots']]],
        );

        return self::SUCCESS;
    }

    private function slotKey(Weekday $weekday, string $time): string
    {
        return $weekday->value.'|'.CarbonImmutable::parse($time)->format('H:i');
    }
}

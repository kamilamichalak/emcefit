<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Usuwa wszystkich fikcyjnych klientów (email @test.pl) wraz z powiązanymi danymi.
 * Kasowanie usera kaskaduje: clients → memberships → reservations / payments /
 * membership_class_groups, oraz makeup_credits. Parą jest test-data:seed.
 */
class TestDataClear extends Command
{
    protected $signature = 'test-data:clear {--force : nie pytaj o potwierdzenie}';

    protected $description = 'Usuwa konta testowe (@test.pl) i ich dane — tylko dev.';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('test-data:clear jest niedozwolone w środowisku produkcyjnym.');

            return self::FAILURE;
        }

        $users = User::where('email', 'like', '%@test.pl')->get();

        if ($users->isEmpty()) {
            $this->info('Brak kont @test.pl do usunięcia.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm("Usunąć {$users->count()} kont @test.pl wraz z karnetami, rezerwacjami, płatnościami i odrobieniami?", true)) {
            $this->line('Anulowano.');

            return self::SUCCESS;
        }

        $deleted = 0;
        DB::transaction(function () use ($users, &$deleted): void {
            foreach ($users as $user) {
                $user->delete(); // kaskada FK sprząta clients + całą resztę
                $deleted++;
            }
        });

        $this->info("Usunięto {$deleted} kont testowych (@test.pl) wraz z powiązanymi danymi.");

        return self::SUCCESS;
    }
}

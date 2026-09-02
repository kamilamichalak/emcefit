<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 17: kto zarejestrował zapis na zajęcia — NULL gdy klient sam,
     * ID admina/trenera gdy zrobił to w jego imieniu. Na razie tylko do statystyk.
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('registered_by_id')->nullable()->after('price_locked')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by_id');
        });
    }
};

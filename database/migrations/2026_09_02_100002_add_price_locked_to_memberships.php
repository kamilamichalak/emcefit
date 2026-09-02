<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 11a: migawka ceny karnetu w momencie założenia — nie zmienia się nawet
     * po późniejszej edycji cennika (Prompt 11).
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->decimal('price_locked', 8, 2)->nullable()->after('membership_type_id');
        });

        // Backfill istniejących rekordów aktualną ceną z ich typu (to dane testowe).
        DB::statement(<<<'SQL'
            UPDATE memberships m
            JOIN membership_types t ON t.id = m.membership_type_id
            SET m.price_locked = t.price
            WHERE m.price_locked IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('price_locked');
        });
    }
};

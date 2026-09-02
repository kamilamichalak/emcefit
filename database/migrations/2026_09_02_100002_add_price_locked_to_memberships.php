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
        // Portable — działa i na MySQL, i na SQLite (bez UPDATE ... JOIN).
        foreach (DB::table('membership_types')->pluck('price', 'id') as $typeId => $price) {
            DB::table('memberships')
                ->where('membership_type_id', $typeId)
                ->whereNull('price_locked')
                ->update(['price_locked' => $price]);
        }
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('price_locked');
        });
    }
};

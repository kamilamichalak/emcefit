<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 15: ikona typu zajęć (nazwa komponentu z lucide-vue-next).
     */
    public function up(): void
    {
        Schema::table('class_types', function (Blueprint $table) {
            $table->string('icon')->default('Dumbbell')->after('color');
        });

        // Dopasowanie ikon do istniejących typów startowych (spec sekcja 18).
        $icons = [
            'Body Pump' => 'Dumbbell',
            'TBC' => 'Activity',
            'TBC Max' => 'Flame',
            'HIIT' => 'HeartPulse',
            'Fit Dance' => 'Music',
            'Fit Dance Step' => 'Music2',
            'Funkcjonal Choreo Step' => 'Footprints',
            'Mix Treningowy' => 'Repeat',
        ];

        foreach ($icons as $name => $icon) {
            DB::table('class_types')->where('name', $name)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('class_types', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};

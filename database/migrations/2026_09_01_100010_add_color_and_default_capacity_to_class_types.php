<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec sekcja 4: class_types dostaje kolor (wizualne oznaczenie na grafiku)
     * i domyslny limit miejsc podpowiadany przy ukladaniu wzorca.
     */
    public function up(): void
    {
        Schema::table('class_types', function (Blueprint $table) {
            $table->string('color', 7)->default('#E91E63')->after('required_equipment')
                ->comment('hex, np. #E91E63 — kolor typu zajec na grafiku');
            $table->unsignedSmallInteger('default_capacity')->default(20)->after('color')
                ->comment('domyslny limit miejsc podpowiadany dla nowych zajec tego typu');
        });
    }

    public function down(): void
    {
        Schema::table('class_types', function (Blueprint $table) {
            $table->dropColumn(['color', 'default_capacity']);
        });
    }
};

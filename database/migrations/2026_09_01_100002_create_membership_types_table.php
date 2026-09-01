<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: membership_types — membership kinds per the club price list,
     * admin-configurable (Phase 1: filled by a seeder — spec 8a).
     */
    public function up(): void
    {
        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mode')->comment('zamkniety | otwarty | bez_limitu | jednorazowe');
            $table->unsignedTinyInteger('sessions_per_week')->nullable()->comment('closed mode: 1..4; otherwise null');
            $table->unsignedInteger('entry_count')->nullable()->comment('open mode: package of X entries; otherwise null');
            $table->string('validity_period_type')->nullable()->comment('miesiac_kalendarzowy | tygodnie_od_pierwszego_wejscia; null for single entry');
            $table->unsignedSmallInteger('validity_period_value')->nullable()->comment('number of months or weeks; null for single entry');
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_types');
    }
};

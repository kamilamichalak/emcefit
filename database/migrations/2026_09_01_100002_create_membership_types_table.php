<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sekcja 4 spec: membership_types — rodzaje karnetow wg cennika klubu,
     * konfigurowane przez admina (w Fazie 1 wypelniane seederem — sekcja 8a).
     */
    public function up(): void
    {
        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->string('tryb')->comment('zamkniety | otwarty | bez_limitu | jednorazowe');
            $table->unsignedTinyInteger('sesje_w_tygodniu')->nullable()->comment('tryb zamkniety: 1..4; inaczej null');
            $table->unsignedInteger('liczba_wejsc')->nullable()->comment('tryb otwarty: pakiet X wejsc; inaczej null');
            $table->string('okres_waznosci_typ')->nullable()->comment('miesiac_kalendarzowy | tygodnie_od_pierwszego_wejscia; null dla wejscia jednorazowego');
            $table->unsignedSmallInteger('okres_waznosci_wartosc')->nullable()->comment('liczba miesiecy albo tygodni; null dla wejscia jednorazowego');
            $table->decimal('cena', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_types');
    }
};

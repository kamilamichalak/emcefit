<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sekcja 4 spec: payments — platnosci rejestrowane recznie na podstawie
     * przelewow bankowych (sekcja 8: brak platnosci online w MVP).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->decimal('kwota', 8, 2);
            $table->date('data_zgloszenia');
            $table->date('data_zaksiegowania')->nullable()->comment('ustawiane recznie przez admina po sprawdzeniu wyciagu; decyduje o kolejnosci na liscie (sekcja 4/8)');
            $table->string('status')->default('oczekuje')->comment('oczekuje | zaksiegowana | anulowana');
            $table->string('tytul_przelewu')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

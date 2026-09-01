<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sekcja 4 spec: clients — rozszerzenie usera o dane specyficzne dla klienta.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('telefon')->nullable();
            $table->date('data_urodzenia')->nullable();
            $table->string('status')->default('aktywny')->comment('aktywny | nieaktywny');
            $table->date('data_dolaczenia')->nullable();
            $table->timestamp('regulamin_zaakceptowany_at')->nullable()->comment('sekcja 8a: zgoda z regulaminem');
            $table->timestamp('oswiadczenie_zdrowotne_at')->nullable()->comment('sekcja 8a: oswiadczenie o braku przeciwwskazan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

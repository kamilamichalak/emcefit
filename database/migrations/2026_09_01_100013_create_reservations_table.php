<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec sekcja 4: reservations — rezerwacja klienta na konkretne wystąpienie zajęć.
     * O kolejności decyduje `confirmed_at` (= data zaksięgowania powiązanej wpłaty).
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_schedule_id')->constrained('class_schedule')->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('oczekuje_platnosci')
                ->comment('oczekuje_platnosci | potwierdzona | waitlist | odwolana | odrobiona');
            $table->timestamp('reported_at');
            $table->timestamp('confirmed_at')->nullable()->comment('= data zaksięgowania powiązanej wpłaty');
            $table->timestamps();

            $table->unique(['client_id', 'class_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

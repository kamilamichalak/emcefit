<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: class_groups — tygodniowy wzorzec grafiku, wersjonowany per
     * miesiac (obowiazuje_od / obowiazuje_do wskazuja pierwszy dzien miesiaca).
     */
    public function up(): void
    {
        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('ISO: 1=poniedzialek .. 7=niedziela; Faza 2 uzywa 1..5');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes')->default(55);
            $table->unsignedSmallInteger('capacity')->comment('limit miejsc');
            $table->date('active_from')->comment('1. dzien miesiaca, od ktorego wzorzec obowiazuje');
            $table->date('active_to')->nullable()->comment('1. dzien ostatniego miesiaca obowiazywania; null = bezterminowo');
            $table->timestamps();

            $table->index(['active_from', 'active_to']);
            $table->index(['weekday', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_groups');
    }
};

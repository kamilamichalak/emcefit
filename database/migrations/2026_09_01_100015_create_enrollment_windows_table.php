<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec sekcja 4: zapisy_miesieczne — czy admin otworzył zapisy klientów na dany
     * miesiąc. Wygenerowanie harmonogramu (class_schedule) NIE otwiera zapisów —
     * to zawsze osobna, świadoma decyzja admina.
     */
    public function up(): void
    {
        Schema::create('enrollment_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('open')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_windows');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec sekcja 4: makeup_credits — prawo do odrobienia po odwołaniu zajęć
     * (przez klienta jako planowana nieobecność LUB z góry przez klub).
     */
    public function up(): void
    {
        Schema::create('makeup_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->boolean('expires_end_of_month')->default(true);
            $table->boolean('used')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('makeup_credits');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: class_schedule — konkretne wystapienie zajec w danym dniu,
     * generowane z class_groups na dany miesiac.
     */
    public function up(): void
    {
        Schema::create('class_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->comment('moze odbiegac od wzorca dla pojedynczego wystapienia');
            $table->string('status')->default('planowane')->comment('planowane | odwolane');
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['class_group_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule');
    }
};

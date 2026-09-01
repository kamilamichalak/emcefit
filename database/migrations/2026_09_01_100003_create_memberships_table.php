<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: memberships — a membership assigned to a client.
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('class_group_id')->nullable()
                ->comment('closed mode only; FK to class_groups added in Phase 2');
            $table->date('first_entry_date')->nullable()->comment('set on the first entry');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('entries_remaining')->nullable()->comment('null = unlimited / not applicable');
            $table->boolean('continuation_confirmed')->default(false)->comment('spec 8a: client confirms themselves; reset monthly');
            $table->timestamps();

            $table->index('class_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

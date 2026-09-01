<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: payments — recorded manually from bank transfers
     * (spec section 8: no online payments in the MVP).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->date('reported_date');
            $table->date('settled_date')->nullable()->comment('set manually by admin after checking the bank statement; drives list/waitlist order (spec 4/8)');
            $table->string('status')->default('oczekuje')->comment('oczekuje | zaksiegowana | anulowana');
            $table->string('transfer_title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

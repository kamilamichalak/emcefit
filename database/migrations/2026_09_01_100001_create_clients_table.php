<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: clients — user extension with client-specific data.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status')->default('aktywny')->comment('aktywny | nieaktywny');
            $table->date('join_date')->nullable();
            $table->timestamp('terms_accepted_at')->nullable()->comment('spec 8a: regulations acceptance');
            $table->timestamp('health_declaration_at')->nullable()->comment('spec 8a: no-contraindications declaration');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

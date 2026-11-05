<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-off collection of real client e-mails from the public /dane form.
     * Matched to client accounts manually later; no uniqueness on purpose.
     */
    public function up(): void
    {
        Schema::create('email_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->timestamp('consent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_submissions');
    }
};

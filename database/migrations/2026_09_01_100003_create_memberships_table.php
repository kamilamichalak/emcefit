<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sekcja 4 spec: memberships — karnet przypisany klientowi.
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('class_group_id')->nullable()
                ->comment('tylko tryb zamkniety; FK do class_groups dojdzie w Fazie 2');
            $table->date('data_pierwszego_wejscia')->nullable()->comment('ustawiane przy pierwszym wejsciu');
            $table->date('data_od')->nullable();
            $table->date('data_do')->nullable();
            $table->integer('wejscia_pozostale')->nullable()->comment('null = bez limitu / nie dotyczy');
            $table->boolean('kontynuacja_potwierdzona')->default(false)->comment('sekcja 8a: klient sam potwierdza; resetowane co miesiac');
            $table->timestamps();

            $table->index('class_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

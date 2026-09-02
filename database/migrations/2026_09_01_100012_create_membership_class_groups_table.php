<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec sekcja 4/13: klient w ramach jednego karnetu zamknietego moze wybrac kilka
     * roznych zajec cyklicznych/tydzien -> relacja wiele-do-wielu memberships<->class_groups.
     * Zastepuje wczesniejsze pole memberships.class_group_id (1:1).
     */
    public function up(): void
    {
        Schema::create('membership_class_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();

            $table->unique(['membership_id', 'class_group_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['class_group_id']);
            // Jawny indeks z create_memberships_table — SQLite nie pozwoli usunąć
            // kolumny, dopóki indeks do niej istnieje (na MySQL też trzeba to zrobić jawnie).
            $table->dropIndex(['class_group_id']);
            $table->dropColumn('class_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('class_group_id')->nullable()->after('membership_type_id')
                ->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('membership_class_groups');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domkniecie TODO z Fazy 1: memberships.class_group_id dostaje FK, gdy tabela
     * class_groups juz istnieje. nullOnDelete — usuniecie wzorca nie kasuje karnetu.
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreign('class_group_id')->references('id')->on('class_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['class_group_id']);
        });
    }
};

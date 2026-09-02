<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 16e: ręczna zmiana typu/ceny karnetu przez admina po jego utworzeniu
     * (np. rabat) — kto, kiedy i dlaczego.
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('modified_by_id')->nullable()->after('registered_by_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('modified_at')->nullable()->after('modified_by_id');
            $table->text('admin_note')->nullable()->after('modified_at');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modified_by_id');
            $table->dropColumn(['modified_at', 'admin_note']);
        });
    }
};

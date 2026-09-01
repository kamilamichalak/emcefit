<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec sekcja 4/12: jednorazowy link aktywacyjny. Po ustawieniu hasla i zaakceptowaniu
     * regulaminu/oswiadczenia przez klienta pole sie wypelnia i link przestaje dzialac.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('invitation_used_at')->nullable()->after('health_declaration_at')
                ->comment('spec 12: moment aktywacji konta przez klienta (link jednorazowy)');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('invitation_used_at');
        });
    }
};

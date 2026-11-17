<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Które powiadomienia mailowe klientka chce dostawać (null = wszystkie). */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('mail_preferences')->nullable()->after('invitation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('mail_preferences');
        });
    }
};

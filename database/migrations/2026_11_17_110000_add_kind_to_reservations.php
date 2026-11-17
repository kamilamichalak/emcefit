<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('kind')->default('stala')->after('status')
                ->comment('stala (z karnetu zamkniętego) | odrobienie | otwarty (wejście z karnetu otwartego)');
            $table->foreignId('makeup_credit_id')->nullable()->after('kind')
                ->constrained('makeup_credits')->nullOnDelete();
            $table->boolean('late_release')->default(false)->after('makeup_credit_id')
                ->comment('zwolnione później niż 1 h przed zajęciami');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('makeup_credit_id');
            $table->dropColumn(['kind', 'late_release']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Forma płatności: przelew / gotowka / blik. Wpłaty zaksięgowane wcześniej dostają „przelew” (decyzja Kamili 26.11) — można zmienić na karcie klientki. */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->after('transfer_title');
        });

        DB::table('payments')->whereNull('method')->update(['method' => 'przelew']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};

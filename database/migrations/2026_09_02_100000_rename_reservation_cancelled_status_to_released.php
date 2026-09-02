<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Prompt 14b: nazewnictwo wprost z regulaminu (pkt 16) — status pojedynczej
     * rezerwacji klienta "odwolana" => "zwolnione". Nie dotyczy class_schedule.
     */
    public function up(): void
    {
        DB::table('reservations')->where('status', 'odwolana')->update(['status' => 'zwolnione']);
    }

    public function down(): void
    {
        DB::table('reservations')->where('status', 'zwolnione')->update(['status' => 'odwolana']);
    }
};

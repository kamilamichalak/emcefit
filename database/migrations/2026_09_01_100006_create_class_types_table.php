<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 4: class_types — slownik typow zajec (Body Pump, TBC, HIIT, ...).
     */
    public function up(): void
    {
        Schema::create('class_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('required_equipment')->nullable()->comment('informacyjnie, np. "sztangi"');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The measurement form has always treated "Label / Occasion" as optional
     * (no asterisk, validated as nullable), but the column was NOT NULL — so
     * saving a measurement without a label threw a 500. A user hit exactly
     * this in production on 2026-08-17 while adding a kanzu measurement.
     */
    public function up(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->string('label')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->string('label')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fabric is sold by the metre, so a line can legitimately be 2.5 or 0.75 of
     * something. The quantity columns were whole numbers, which forced staff to
     * round. Both now carry two decimal places.
     *
     * Shelf items are still validated as whole numbers in the controller —
     * their stock is counted in units, and half a cap does not exist.
     */
    public function up(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(1)->change();
            $table->decimal('ridhaa_qty', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
            $table->integer('ridhaa_qty')->default(0)->change();
        });
    }
};

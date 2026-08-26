<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The minimum-stock figure differs for every product, so the system should
     * not invent one. It defaulted to 5, which staff then had to clear on each
     * product — and a 0 they had set was silently restored to 5 on the next
     * edit. The column is now nullable with no default: blank means "not set".
     *
     * Existing values are left exactly as they are; this only stops a figure
     * being applied that nobody chose.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->nullable()->default(null)->change();
        });

        Schema::table('collection_variants', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(5)->change();
        });

        Schema::table('collection_variants', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(5)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks the moment an invoice gave its stock back, so it can only ever
     * happen once. Without it, voiding an invoice and then deleting it would
     * return the same units twice and inflate the shop's stock.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('stock_restored_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('stock_restored_at');
        });
    }
};

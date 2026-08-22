<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ridhaa is not carried as stock — each one is a new item, so it is not in
     * the fabric catalogue and has no fixed price. Staff type its name,
     * quantity and price straight onto the invoice line at billing time.
     */
    public function up(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->string('ridhaa_name')->nullable()->after('fabric_cost');
            $table->integer('ridhaa_qty')->default(0)->after('ridhaa_name');
            $table->decimal('ridhaa_price', 10, 2)->default(0)->after('ridhaa_qty');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropColumn(['ridhaa_name', 'ridhaa_qty', 'ridhaa_price']);
        });
    }
};

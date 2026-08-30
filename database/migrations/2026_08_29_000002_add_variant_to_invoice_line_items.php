<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records WHICH variation was sold.
     *
     * Variations existed but selling ignored them: a product with three sizes
     * sold at the product price and took stock off the product as a whole, so
     * nobody could tell which size left the shelf or charge a variation its own
     * price. The sale now points at the exact variation.
     */
    public function up(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreignId('collection_variant_id')->nullable()->after('collection_id')
                  ->constrained('collection_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_variant_id');
        });
    }
};

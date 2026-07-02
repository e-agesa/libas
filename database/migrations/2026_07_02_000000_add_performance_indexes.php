<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hot-path indexes for the live storefront + back-office reports.
 *
 * The public shop (ShopController::index) filters every request on
 * collections.(status, show_on_shop, stock_qty) and sorts by name — none of
 * which were indexed, forcing a full scan + filesort over all ~342 rows.
 * Dashboard / Reports / Invoice list filter invoices on (status, type, date),
 * and Reports groups invoice_line_items by item_type — all unindexed.
 *
 * Safe/additive: only CREATE INDEX, no data or column changes. Existing FK
 * indexes (category_id, invoice_id, movable_*) already cover their columns and
 * are intentionally left alone. stock_movements already has (movable_type,
 * movable_id) and type indexes, so nothing to add there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Storefront/POS predicate: status + show_on_shop + stock_qty, ORDER BY name.
            $table->index(['status', 'show_on_shop', 'stock_qty'], 'collections_shop_idx');
            $table->index('name', 'collections_name_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // status: Dashboard pending count, Report revenue/outstanding, list filter.
            $table->index('status', 'invoices_status_idx');
            // type: invoice-vs-quotation list filter.
            $table->index('type', 'invoices_type_idx');
            // date: whereDate/whereBetween/whereMonth + latest('date') everywhere.
            $table->index('date', 'invoices_date_idx');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            // Report groups/filters revenue by item_type.
            $table->index('item_type', 'invoice_line_items_item_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_shop_idx');
            $table->dropIndex('collections_name_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_idx');
            $table->dropIndex('invoices_type_idx');
            $table->dropIndex('invoices_date_idx');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropIndex('invoice_line_items_item_type_idx');
        });
    }
};

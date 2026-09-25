<?php

use App\Models\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Puts the catalogue back on the website, and rescues stock stranded on
     * variations.
     *
     * Two separate faults left whole categories missing from libasulanwar.com.
     *
     * The first: collections.show_on_shop defaults to false and the product form
     * never set it, so every product added through the back office was invisible
     * to customers until somebody found the per-product toggle buried in
     * Settings. That is why Jhabla Izaars showed 19 of its 35, and why 236
     * products in all were hidden. The form now carries the switch and new
     * products arrive visible; this turns on the ones already entered.
     *
     * The second: a handful of products show a stock of nought while their
     * variations still hold units — the residue of the product form overwriting
     * the roll-up, fixed on 31 August. The shop and the till both list on that
     * roll-up, so those products are invisible everywhere despite being in
     * stock. Recomputing the figure from the variations restores them.
     *
     * Inactive products are left hidden: "inactive" is a deliberate choice and
     * not something to overturn here.
     */
    public function up(): void
    {
        $shown = DB::table('collections')
            ->where('status', 'active')
            ->where('show_on_shop', false)
            ->update(['show_on_shop' => true]);

        $repaired = 0;

        // Only products whose own figure disagrees with their variations; a
        // genuinely sold-out product is left alone.
        Collection::query()
            ->where('stock_qty', '<=', 0)
            ->whereHas('variants', fn ($q) => $q->where('stock_qty', '>', 0))
            ->chunkById(100, function ($collections) use (&$repaired) {
                foreach ($collections as $collection) {
                    $collection->recalcStockFromVariants();
                    $repaired++;
                }
            });

        logger()->info("Shop visibility: {$shown} products made visible, {$repaired} stock figures repaired.");
    }

    public function down(): void
    {
        // Deliberately not reversed. Which products belong on the website is the
        // shop's decision, editable per product on the form and in bulk under
        // Settings; putting them all back out of sight would be the destructive
        // move, not this.
    }
};

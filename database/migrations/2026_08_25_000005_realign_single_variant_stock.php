<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sales and stock adjustments move the product's stock figure but did not
     * touch its variation, so the two had already drifted apart. For every
     * product with exactly one variation, set that variation back to the
     * product's own count — the product figure is what the tills have been
     * selling against, so it is the truthful one.
     *
     * Products with several variations are left alone: their stock is kept per
     * variation and there is no safe way to split a product total across them.
     */
    public function up(): void
    {
        DB::statement('
            UPDATE collection_variants v
            JOIN collections c ON c.id = v.collection_id
            JOIN (
                SELECT collection_id FROM collection_variants
                GROUP BY collection_id HAVING COUNT(*) = 1
            ) single ON single.collection_id = v.collection_id
            SET v.stock_qty = c.stock_qty
            WHERE v.stock_qty <> c.stock_qty
        ');
    }

    public function down(): void
    {
        // Stock counts; leave them as corrected.
    }
};

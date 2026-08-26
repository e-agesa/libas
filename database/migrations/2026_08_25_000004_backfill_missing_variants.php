<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Products created after the variations were introduced never received one,
     * because creating a product did not create a variation. That left the
     * Variations panel empty for them and product stock ahead of variation
     * stock. This gives every product still missing one the same single
     * variation the original backfill created, carrying its own stock.
     */
    public function up(): void
    {
        $now = now();

        DB::table('collections')
            ->select('collections.id', 'collections.size', 'collections.color',
                     'collections.stock_qty', 'collections.low_stock_threshold', 'collections.status')
            ->leftJoin('collection_variants', 'collection_variants.collection_id', '=', 'collections.id')
            ->whereNull('collection_variants.id')
            ->orderBy('collections.id')
            ->chunk(200, function ($rows) use ($now) {
                $variants = [];
                foreach ($rows as $row) {
                    $variants[] = [
                        'collection_id' => $row->id,
                        'size' => $row->size,
                        'color' => $row->color,
                        'design' => null,
                        'sku' => null,
                        'price' => null,
                        'stock_qty' => $row->stock_qty,
                        'reserved_qty' => 0,
                        'low_stock_threshold' => $row->low_stock_threshold,
                        'status' => $row->status,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($variants) {
                    DB::table('collection_variants')->insert($variants);
                }
            });
    }

    public function down(): void
    {
        // Leaves the variations in place: they now carry real stock counts.
    }
};

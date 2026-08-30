<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stops a product's own photograph being erased by variation photos.
     *
     * The product form writes collections.image_path straight to the column and
     * never made a gallery row. Every image operation, though, re-derives
     * image_path from the gallery — so on a product whose photo lived only in
     * the column, uploading the first variation photo replaced it, and deleting
     * that variation photo again set it to NULL. The picture was still on disk;
     * the product simply stopped pointing at it.
     *
     * The controller now files the form's photo in the gallery. This does the
     * same for every product already carrying one, so the existing catalogue is
     * safe from the moment this runs.
     */
    public function up(): void
    {
        DB::table('collections')
            ->whereNotNull('image_path')
            ->where('image_path', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($collections) {
                $rows = [];
                $now = now();

                foreach ($collections as $collection) {
                    // Already represented in the gallery — nothing to protect.
                    $exists = DB::table('collection_images')
                        ->where('collection_id', $collection->id)
                        ->where('path', $collection->image_path)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $hasPrimary = DB::table('collection_images')
                        ->where('collection_id', $collection->id)
                        ->where('is_primary', true)
                        ->exists();

                    $rows[] = [
                        'collection_id' => $collection->id,
                        'collection_variant_id' => null,
                        'path' => $collection->image_path,
                        'alt' => $collection->name,
                        // Do not demote a variation that already leads.
                        'is_primary' => $hasPrimary ? 0 : 1,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('collection_images')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // Leaving the rows in place is harmless: they name photos that exist and
        // that the products were already using.
    }
};

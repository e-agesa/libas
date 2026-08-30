<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gives each variation its own lead photograph, and repairs the products
     * whose photograph never showed up.
     *
     * Photographs uploaded through "Variations & Photos" went into the gallery
     * table, but every listing — POS, the invoice picker, inventory, the shop —
     * reads a single image_path column. Nothing copied one to the other, so a
     * product could have several photographs on file and still show a grey
     * placeholder everywhere it was sold. Variations had no column at all, so
     * three designs of one product all showed the same picture.
     *
     * A denormalised column rather than a join, because these lists render
     * hundreds of rows at a time and the gallery is only ever read on the
     * product's own edit screen.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('collection_variants', 'image_path')) {
            Schema::table('collection_variants', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('sku');
            });
        }

        // A variation's own photograph: its primary, else its first.
        DB::statement("
            UPDATE collection_variants v
            JOIN (
                SELECT collection_variant_id, MIN(id) AS image_id
                FROM (
                    SELECT id, collection_variant_id
                    FROM collection_images
                    WHERE collection_variant_id IS NOT NULL
                    ORDER BY is_primary DESC, sort_order ASC, id ASC
                ) ordered
                GROUP BY collection_variant_id
            ) pick ON pick.collection_variant_id = v.id
            JOIN collection_images i ON i.id = pick.image_id
            SET v.image_path = i.path
            WHERE v.image_path IS NULL
        ");

        // Products showing nothing while their gallery holds photographs.
        DB::statement("
            UPDATE collections c
            JOIN (
                SELECT collection_id, MIN(id) AS image_id
                FROM (
                    SELECT id, collection_id
                    FROM collection_images
                    ORDER BY is_primary DESC, sort_order ASC, id ASC
                ) ordered
                GROUP BY collection_id
            ) pick ON pick.collection_id = c.id
            JOIN collection_images i ON i.id = pick.image_id
            SET c.image_path = i.path
            WHERE c.image_path IS NULL OR c.image_path = ''
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('collection_variants', 'image_path')) {
            Schema::table('collection_variants', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};

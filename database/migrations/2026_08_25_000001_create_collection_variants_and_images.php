<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One product, many variations.
     *
     * Today every size/colour combination is its own product row, which is why
     * the catalogue runs to hundreds of near-duplicates. A product now keeps
     * its variations in one place: size, colour and design each vary an item,
     * every combination holds its own stock, and photographs can belong to the
     * product as a whole or to one particular variation.
     *
     * This migration is ADDITIVE. Nothing on `collections` is dropped or
     * rewritten: every existing product keeps its own stock_qty and price, and
     * selling continues to work exactly as before while the new structure is
     * filled in behind it.
     */
    public function up(): void
    {
        Schema::create('collection_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();

            // The three axes the shop actually varies an item by.
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->string('design')->nullable();      // "Design 1", "Design 2", ...

            $table->string('sku')->nullable()->unique();
            // Null price means "same as the parent product" — most variations
            // are priced identically and should not have to repeat it.
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock_qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['collection_id', 'status']);
            $table->index(['size', 'color', 'design']);
        });

        Schema::create('collection_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            // Attached to one variation, or null for a photo of the product itself.
            $table->foreignId('collection_variant_id')->nullable()
                  ->constrained('collection_variants')->nullOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['collection_id', 'sort_order']);
        });

        // ---- Backfill, so nothing is lost and nothing looks empty ----

        // 1. Every existing product becomes a product with exactly one
        //    variation carrying its current size, colour, stock and price.
        //    Stock totals are therefore unchanged.
        DB::table('collections')->orderBy('id')->chunkById(200, function ($rows) {
            $now = now();
            $variants = [];
            foreach ($rows as $row) {
                $variants[] = [
                    'collection_id' => $row->id,
                    'size' => $row->size,
                    'color' => $row->color,
                    'design' => null,
                    // The unique SKU stays on the product; a variation SKU is
                    // left blank rather than risking a collision.
                    'sku' => null,
                    'price' => null,             // inherit the product price
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

        // 2. Any photograph already on a product becomes its first image, so
        //    the new gallery is not empty on day one.
        DB::table('collections')->whereNotNull('image_path')->where('image_path', '!=', '')
            ->orderBy('id')->chunkById(200, function ($rows) {
                $now = now();
                $images = [];
                foreach ($rows as $row) {
                    $images[] = [
                        'collection_id' => $row->id,
                        'collection_variant_id' => null,
                        'path' => $row->image_path,
                        'alt' => $row->name,
                        'is_primary' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($images) {
                    DB::table('collection_images')->insert($images);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_images');
        Schema::dropIfExists('collection_variants');
    }
};

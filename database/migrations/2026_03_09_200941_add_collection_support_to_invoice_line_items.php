<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            // Line item type: 'custom' (tailored) or 'collection' (off-the-shelf)
            $table->enum('item_type', ['custom', 'collection'])->default('custom')->after('invoice_id');

            // Collection reference (for shelf items)
            $table->foreignId('collection_id')->nullable()->after('fabric_id')
                ->constrained('collections')->nullOnDelete();

            // Description (useful for collection items or custom labels)
            $table->string('description')->nullable()->after('collection_id');

            // Unit price (for collection items)
            $table->decimal('unit_price', 10, 2)->default(0)->after('description');
        });

        // Make existing FK columns nullable for collection items
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['measurement_id']);
            $table->dropForeign(['fabric_id']);
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->change();
            $table->unsignedBigInteger('measurement_id')->nullable()->change();
            $table->unsignedBigInteger('fabric_id')->nullable()->change();
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('measurement_id')->references('id')->on('measurements')->cascadeOnDelete();
            $table->foreign('fabric_id')->references('id')->on('fabrics')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropForeign(['collection_id']);
            $table->dropColumn(['item_type', 'collection_id', 'description', 'unit_price']);
        });
    }
};

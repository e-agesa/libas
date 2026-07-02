<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotent: production already has these columns but never recorded this
        // migration, so guard each add to avoid "Duplicate column" on migrate.
        Schema::table('garment_types', function (Blueprint $table) {
            if (! Schema::hasColumn('garment_types', 'base_price')) {
                $table->decimal('base_price', 10, 2)->default(0)->after('color');
            }
            if (! Schema::hasColumn('garment_types', 'default_fabric_qty')) {
                $table->decimal('default_fabric_qty', 5, 2)->default(2.00)->after('base_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garment_types', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['base_price', 'default_fabric_qty'],
                fn ($c) => Schema::hasColumn('garment_types', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};

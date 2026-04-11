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
        Schema::table('garment_types', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->default(0)->after('color');
            $table->decimal('default_fabric_qty', 5, 2)->default(2.00)->after('base_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garment_types', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'default_fabric_qty']);
        });
    }
};

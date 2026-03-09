<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movable_type'); // App\Models\Fabric or App\Models\Collection
            $table->unsignedBigInteger('movable_id');
            $table->string('type'); // purchase, sale, adjustment, reservation, release, return, waste
            $table->integer('quantity'); // positive = in, negative = out
            $table->integer('balance_after'); // running balance after this movement
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference')->nullable(); // invoice number, PO number, etc.
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['movable_type', 'movable_id']);
            $table->index('type');
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Add reserved_qty to fabrics for material reservation
        Schema::table('fabrics', function (Blueprint $table) {
            $table->integer('reserved_qty')->default(0)->after('stock_qty');
            $table->integer('low_stock_threshold')->default(10)->after('reserved_qty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn(['reserved_qty', 'low_stock_threshold']);
        });
    }
};

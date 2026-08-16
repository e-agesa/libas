<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration: ensure the "Ridhaa" fabric requested in the client
     * review of 16 Aug 2026 exists on every environment (incl. production,
     * where seeders are not run). Idempotent — skips if already present.
     */
    public function up(): void
    {
        if (DB::table('fabrics')->where('name', 'Ridhaa')->exists()) {
            return;
        }

        DB::table('fabrics')->insert([
            'name' => 'Ridhaa',
            'type' => 'Ridhaa',
            'color' => '#1F2937',
            'price_per_unit' => 1200, // placeholder — confirm real price with client
            'stock_qty' => 30,
            'reserved_qty' => 0,
            'low_stock_threshold' => 10,
            'supplier' => 'Mombasa Textiles',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data seed — intentionally kept on rollback (may have live references).
    }
};

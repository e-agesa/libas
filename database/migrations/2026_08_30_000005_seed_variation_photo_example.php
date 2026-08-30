<?php

use Database\Seeders\VariationImageExampleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Puts the worked example on the live system.
     *
     * This host has no SSH and no console, so `db:seed` can never be run there:
     * the deploy's only hook into the database is `migrate --force`. A one-off
     * piece of demonstration data therefore has to arrive as a migration, or it
     * exists on a developer's machine and nowhere anybody can look at it.
     *
     * The seeder builds its own pictures and replaces its own product, so this
     * is safe whether it has run before or not.
     */
    public function up(): void
    {
        try {
            (new VariationImageExampleSeeder())->run();
        } catch (\Throwable $e) {
            // A demonstration product is not worth failing a deploy over — the
            // migrations that follow carry real fixes.
            report($e);
        }
    }

    public function down(): void
    {
        \App\Models\Collection::where('sku', 'DEMO-VAR-PHOTOS')->delete();
    }
};

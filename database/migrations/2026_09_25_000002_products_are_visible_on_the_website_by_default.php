<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product belongs on the website unless somebody says otherwise.
     *
     * The column has defaulted to false since it was added, so anything created
     * without explicitly setting it — an import, a seeder, any code path that is
     * not the product form — arrives hidden from customers. The form was taught
     * to set it, but a default that has to be worked around is the wrong default:
     * the shop's own instruction is that everything shows, and they hide the few
     * exceptions themselves.
     *
     * This also catches up anything created between the form fix and now.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('show_on_shop')->default(true)->change();
        });

        // Anything still hidden that nobody deliberately deactivated.
        DB::table('collections')
            ->where('status', 'active')
            ->where('show_on_shop', false)
            ->update(['show_on_shop' => true]);
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('show_on_shop')->default(false)->change();
        });
    }
};

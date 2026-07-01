<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS supports walk-in / anonymous sales (a `walk_in_name` with no client account),
 * and the POS receipt already renders without a client. But `client_id` was created
 * NOT NULL, so a genuine walk-in sale threw "1048 Column 'client_id' cannot be null".
 * Make it nullable while keeping the cascading foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }
};

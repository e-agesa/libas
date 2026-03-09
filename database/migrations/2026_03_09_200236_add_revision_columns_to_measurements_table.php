<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->unsignedInteger('revision')->default(1)->after('parent_id');

            $table->foreign('parent_id')
                ->references('id')
                ->on('measurements')
                ->nullOnDelete();

            $table->index(['contact_id', 'garment_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['contact_id', 'garment_type', 'parent_id']);
            $table->dropColumn(['parent_id', 'revision']);
        });
    }
};

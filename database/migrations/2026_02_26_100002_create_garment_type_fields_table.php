<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garment_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_type_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['garment_type_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garment_type_fields');
    }
};

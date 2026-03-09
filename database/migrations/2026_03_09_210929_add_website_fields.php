<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add show_on_shop toggle to collections
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('show_on_shop')->default(false)->after('status');
        });

        // Add website/shop content fields to company settings
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('hero_title')->nullable()->after('footer_text');
            $table->string('hero_subtitle')->nullable()->after('hero_title');
            $table->string('hero_badge')->nullable()->after('hero_subtitle');
            $table->string('shop_banner_path')->nullable()->after('hero_badge');
            $table->string('whatsapp_number')->nullable()->after('shop_banner_path');
            $table->string('instagram')->nullable()->after('whatsapp_number');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('tiktok')->nullable()->after('facebook');
            $table->text('about_text')->nullable()->after('tiktok');
            $table->boolean('shop_enabled')->default(true)->after('about_text');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('show_on_shop');
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_title', 'hero_subtitle', 'hero_badge', 'shop_banner_path',
                'whatsapp_number', 'instagram', 'facebook', 'tiktok', 'about_text', 'shop_enabled',
            ]);
        });
    }
};

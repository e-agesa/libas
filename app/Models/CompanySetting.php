<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    protected $table = 'company_settings';

    protected $fillable = [
        'business_name',
        'tagline',
        'phone',
        'email',
        'address',
        'tax_number',
        'logo_path',
        'footer_text',
        'hero_title',
        'hero_subtitle',
        'hero_badge',
        'shop_banner_path',
        'whatsapp_number',
        'instagram',
        'facebook',
        'tiktok',
        'about_text',
        'shop_enabled',
    ];

    /**
     * Get the single company settings row (always ID=1).
     */
    public static function get(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['business_name' => 'Libas TMS', 'tagline' => 'Tailor Management System']
        );
    }

    /**
     * Get the public URL of the logo for web use.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) return null;
        return Storage::url($this->logo_path);
    }

    /**
     * Get the logo as base64 data URI for embedding in PDFs.
     */
    public function getLogoBase64Attribute(): ?string
    {
        if (!$this->logo_path) return null;
        if (!Storage::exists($this->logo_path)) return null;

        $content = Storage::get($this->logo_path);
        $mime = Storage::mimeType($this->logo_path) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    protected $appends = ['logo_url'];
}

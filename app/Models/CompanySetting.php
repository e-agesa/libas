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
     * Where the logo actually sits on the public disk.
     *
     * The upload has always written the file to the public disk as `logos/x.jpg`
     * while recording it as `public/logos/x.jpg` — a Laravel 5 habit. Storage::url()
     * happens to forgive that prefix, which is why the logo appeared on screen;
     * Storage::exists() does not, and it was being asked of the *local* disk, so
     * every PDF quietly decided there was no logo and printed none. Both readers
     * now go through here: one disk, one path shape, old rows still understood.
     */
    protected function logoRelativePath(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return ltrim(preg_replace('#^public/#', '', $this->logo_path), '/');
    }

    /**
     * Get the public URL of the logo for web use.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->logoRelativePath();

        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        $url = $disk->url($path);

        // Cache-bust on file change so a new logo reaches returning visitors.
        try {
            return $url . '?v=' . $disk->lastModified($path);
        } catch (\Throwable $e) {
            return $url;
        }
    }

    /**
     * Get the logo as base64 data URI for embedding in PDFs.
     *
     * dompdf cannot fetch a URL from this host, so the bytes have to travel
     * inside the document.
     */
    public function getLogoBase64Attribute(): ?string
    {
        $path = $this->logoRelativePath();

        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $content = $disk->get($path);
            $mime = $disk->mimeType($path) ?: 'image/png';
        } catch (\Throwable $e) {
            // A logo we cannot read must not take the whole receipt down with it.
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    protected $appends = ['logo_url'];
}

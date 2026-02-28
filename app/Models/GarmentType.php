<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GarmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function fields()
    {
        return $this->hasMany(GarmentTypeField::class)->orderBy('sort_order');
    }

    public static function generateSlug(string $name): string
    {
        $slug = Str::slug($name, '_');
        $original = $slug;
        $count = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $original . '_' . $count++;
        }
        return $slug;
    }

    /**
     * Map hex color to pre-built Tailwind class strings.
     * Full class names must appear verbatim in source for Tailwind purge to work.
     */
    public function getTailwindClassesAttribute(): array
    {
        return self::hexToTailwind($this->color);
    }

    public static function hexToTailwind(string $hex): array
    {
        return match ($hex) {
            '#22c55e' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'border' => 'border-green-200', 'dot' => 'bg-green-500'],
            '#3b82f6' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'border' => 'border-blue-200', 'dot' => 'bg-blue-500'],
            '#a16207' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500'],
            '#8b5cf6' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'border' => 'border-purple-200', 'dot' => 'bg-purple-500'],
            '#ef4444' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'border' => 'border-red-200', 'dot' => 'bg-red-500'],
            '#14b8a6' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-800', 'border' => 'border-teal-200', 'dot' => 'bg-teal-500'],
            '#6366f1' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'border' => 'border-indigo-200', 'dot' => 'bg-indigo-500'],
            '#ec4899' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-800', 'border' => 'border-pink-200', 'dot' => 'bg-pink-500'],
            '#f97316' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'border' => 'border-orange-200', 'dot' => 'bg-orange-500'],
            '#06b6d4' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'border' => 'border-cyan-200', 'dot' => 'bg-cyan-500'],
            '#84cc16' => ['bg' => 'bg-lime-100', 'text' => 'text-lime-800', 'border' => 'border-lime-200', 'dot' => 'bg-lime-500'],
            '#f43f5e' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-800', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
            default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'border' => 'border-gray-200', 'dot' => 'bg-gray-500'],
        };
    }

    protected $appends = ['tailwind_classes'];
}

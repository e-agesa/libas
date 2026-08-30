<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A photograph of a product. Attached to one variation when the designs look
 * different from each other, or to the product as a whole when they do not.
 */
class CollectionImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'collection_variant_id',
        'path',
        'alt',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            // A multipart upload sends the variation id as text. Without this
            // the freshly-created row came back as "12", and the modal — which
            // matches on identity — filed the photo under no variation at all
            // until the page was reloaded.
            'collection_variant_id' => 'integer',
        ];
    }

    protected $appends = ['url'];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function variant()
    {
        return $this->belongsTo(CollectionVariant::class, 'collection_variant_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        // Older records stored a path that is already web-visible.
        return str_starts_with($this->path, 'http') || str_starts_with($this->path, '/')
            ? $this->path
            : Storage::url($this->path);
    }
}

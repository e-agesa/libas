<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One buyable variation of a product: a size, a colour and a design, each
 * combination holding its own stock.
 */
class CollectionVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'size',
        'color',
        'design',
        'sku',
        'image_path',
        'price',
        'stock_qty',
        'reserved_qty',
        'low_stock_threshold',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_qty' => 'integer',
            'reserved_qty' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    protected $appends = ['label', 'effective_price', 'available_qty', 'image_url'];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function images()
    {
        return $this->hasMany(CollectionImage::class)->orderBy('sort_order');
    }

    /**
     * The variation's own photograph, falling back to the product's. Three
     * designs of one product are three different things to look at, so the
     * till and the picker must not show all of them the same picture.
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return Collection::photoUrl($this->image_path);
        }

        return $this->relationLoaded('collection') ? $this->collection?->image_url : null;
    }

    /**
     * Keep image_path in step with this variation's gallery, so every listing
     * can keep reading one cheap column instead of joining.
     */
    public function syncImagePathFromGallery(): void
    {
        $primary = $this->images()->orderByDesc('is_primary')->orderBy('sort_order')->first();

        if ($primary && $primary->path !== $this->image_path) {
            $this->forceFill(['image_path' => $primary->path])->save();
        } elseif (! $primary && $this->image_path) {
            $this->forceFill(['image_path' => null])->save();
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_qty', '>', 0);
    }

    /**
     * How this variation reads to a person: "21.5 · White · Design 2".
     */
    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->size, $this->color, $this->design]);

        return $parts ? implode(' · ', $parts) : 'Standard';
    }

    /**
     * A variation priced the same as its product leaves price null.
     *
     * Falls back to the product only when that product is already in memory.
     * Reaching for it otherwise fires a query per variation — hundreds on the
     * till — and, worse, attaches a full copy of the product to every variation
     * in the response, cost price included. Callers that need the resolved
     * figure should eager-load `collection`; the till and the picker do their
     * own fallback from the product they already hold.
     */
    public function getEffectivePriceAttribute(): ?float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        if (! $this->relationLoaded('collection')) {
            return null;
        }

        return (float) ($this->collection?->price ?? 0);
    }

    public function getAvailableQtyAttribute(): int
    {
        return max(0, (int) $this->stock_qty - (int) $this->reserved_qty);
    }

    public function isLowStock(): bool
    {
        // No minimum set for this product: only flag it once it has run out.
        if ($this->low_stock_threshold === null) {
            return $this->available_qty <= 0;
        }

        return $this->available_qty <= $this->low_stock_threshold;
    }
}

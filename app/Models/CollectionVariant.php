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

    protected $appends = ['label', 'effective_price', 'available_qty'];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function images()
    {
        return $this->hasMany(CollectionImage::class)->orderBy('sort_order');
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
     */
    public function getEffectivePriceAttribute()
    {
        return $this->price !== null
            ? (float) $this->price
            : (float) ($this->collection?->price ?? 0);
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

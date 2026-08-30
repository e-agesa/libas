<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'image_path',
        'size',
        'color',
        'price',
        'cost_price',
        'stock_qty',
        'low_stock_threshold',
        'status',
        'show_on_shop',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        // image_path is kept in step with the gallery (see syncImagePathFromGallery),
        // so every screen keeps reading one cheap column instead of a join.
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * Keep the product's headline photo equal to its primary gallery image.
     *
     * Photos uploaded through Variations & Photos land in collection_images,
     * but every listing (POS, shop, inventory, invoices) reads image_path — so
     * without this, a photo could be uploaded and appear nowhere.
     */
    public function syncImagePathFromGallery(): void
    {
        $primary = $this->images()->orderByDesc('is_primary')->orderBy('sort_order')->first();

        if ($primary && $primary->path !== $this->image_path) {
            $this->forceFill(['image_path' => $primary->path])->save();
        } elseif (!$primary && $this->image_path && $this->images()->count() === 0) {
            // every gallery photo was deleted
            $this->forceFill(['image_path' => null])->save();
        }
    }

    /**
     * A product holding several variations owns no stock of its own: its figure
     * is the sum of theirs. Used after a variation is sold or returned.
     */
    public function recalcStockFromVariants(): void
    {
        if ($this->variants()->count() === 0) {
            return;
        }

        $this->forceFill(['stock_qty' => (int) $this->variants()->sum('stock_qty')])->save();
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function variants()
    {
        return $this->hasMany(CollectionVariant::class)->orderBy('sort_order');
    }

    public function images()
    {
        return $this->hasMany(CollectionImage::class)->orderBy('sort_order');
    }

    /**
     * Stock held across every variation. While selling still runs off the
     * product-level stock_qty, this is the figure to compare it against.
     */
    /**
     * Until the tills sell from variations, the product figure is the source of
     * truth. When a product has exactly one variation, keep that variation equal
     * to it so the two never silently disagree after a sale or an adjustment.
     * Products with several variations are left alone — their stock is managed
     * per variation.
     */
    public function syncSingleVariantStock(): void
    {
        if ($this->variants()->count() !== 1) {
            return;
        }

        $this->variants()->update(['stock_qty' => (int) $this->stock_qty]);
    }

    public function variantStock(): int
    {
        return (int) $this->variants()->sum('stock_qty');
    }

    public function category()
    {
        return $this->belongsTo(CollectionCategory::class, 'category_id');
    }

    public function invoiceLineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'movable');
    }

    public function isLowStock(): bool
    {
        // No minimum set for this product: only flag it once it has run out.
        if ($this->low_stock_threshold === null) {
            return $this->stock_qty <= 0;
        }

        return $this->stock_qty <= $this->low_stock_threshold;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_qty', '>', 0);
    }
}

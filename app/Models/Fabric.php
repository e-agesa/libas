<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fabric extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'color',
        'price_per_unit',
        'stock_qty',
        'reserved_qty',
        'low_stock_threshold',
        'supplier',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
        ];
    }

    public function invoiceLineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'movable');
    }

    public function getAvailableQtyAttribute(): int
    {
        return max(0, $this->stock_qty - $this->reserved_qty);
    }

    public function isLowStock(int $threshold = null): bool
    {
        return $this->stock_qty <= ($threshold ?? $this->low_stock_threshold ?? 10);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fabric extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'color',
        'price_per_unit',
        'stock_qty',
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

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock_qty < $threshold;
    }
}

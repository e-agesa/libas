<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'item_type',
        'contact_id',
        'measurement_id',
        'fabric_id',
        'collection_id',
        'description',
        'unit_price',
        'quantity',
        'craftsmanship_fee',
        'fabric_cost',
        'ridhaa_name',
        'ridhaa_qty',
        'ridhaa_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'ridhaa_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'craftsmanship_fee' => 'decimal:2',
            'fabric_cost' => 'decimal:2',
            'ridhaa_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function measurement()
    {
        return $this->belongsTo(Measurement::class);
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function isCustom(): bool
    {
        return $this->item_type === 'custom';
    }

    public function isCollection(): bool
    {
        return $this->item_type === 'collection';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'contact_id',
        'measurement_id',
        'fabric_id',
        'quantity',
        'craftsmanship_fee',
        'fabric_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'craftsmanship_fee' => 'decimal:2',
            'fabric_cost' => 'decimal:2',
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
}

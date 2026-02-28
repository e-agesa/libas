<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'invoice_number',
        'type',
        'date',
        'status',
        'subtotal',
        'discount',
        'discount_type',
        'tax',
        'total',
        'amount_paid',
        'balance',
        'due_date',
        'payment_method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'draft' => 'gray',
            'issued' => 'blue',
            'paid' => 'green',
            'overdue' => 'red',
            'voided' => 'gray',
            default => 'gray',
        };
    }

    public static function generateInvoiceNumber(): string
    {
        $latest = static::where('type', 'invoice')->latest('id')->first();
        $number = $latest ? intval(substr($latest->invoice_number, 4)) + 1 : 1;
        return 'INV-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public static function generateQuoteNumber(): string
    {
        $latest = static::where('type', 'quotation')->latest('id')->first();
        $number = $latest ? intval(substr($latest->invoice_number, 4)) + 1 : 1;
        return 'QUO-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}

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

    /**
     * Atomically compute the next sequential document number for a prefix.
     *
     * Scopes by the prefix itself (e.g. "INV-%") — NOT by `type` — so the POS
     * ("POS-"), shop ("WEB-") and quotation ("QUO-") sequences can never bleed
     * into the invoice ("INV-") counter. Derives the next value from the highest
     * existing numeric suffix (never from the newest row by id), and takes a
     * pessimistic lock on that row so concurrent callers serialize instead of
     * minting the same number.
     *
     * Call inside a DB transaction for the lock to hold across the insert.
     */
    public static function nextNumber(string $prefix, int $pad = 4): string
    {
        // MySQL SUBSTRING is 1-indexed; the numeric part starts after "PREFIX-".
        $start = strlen($prefix) + 2;

        $highest = static::where('invoice_number', 'like', $prefix . '-%')
            ->orderByRaw("CAST(SUBSTRING(invoice_number, {$start}) AS UNSIGNED) DESC")
            ->lockForUpdate()
            ->value('invoice_number');

        // PHP substr is 0-indexed; "PREFIX-" is (strlen + 1) chars.
        $lastNum = $highest ? (int) substr($highest, strlen($prefix) + 1) : 0;

        return $prefix . '-' . str_pad((string) ($lastNum + 1), $pad, '0', STR_PAD_LEFT);
    }

    public static function generateInvoiceNumber(): string
    {
        return static::nextNumber('INV', 4);
    }

    public static function generateQuoteNumber(): string
    {
        return static::nextNumber('QUO', 4);
    }
}

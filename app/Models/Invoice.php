<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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

    /**
     * Run a numbering transaction that self-heals a colliding invoice_number.
     *
     * nextNumber()'s pessimistic lock serialises callers only once a row exists
     * for the prefix. For the FIRST insert of a prefix the locking SELECT matches
     * zero rows, so InnoDB takes only a (mutually-compatible) gap lock — two
     * concurrent "first" callers can both read null and both mint PREFIX-0001.
     * The UNIQUE index is the correctness backstop; this wrapper turns the
     * resulting duplicate-key (1062) into a transparent retry instead of a 500.
     *
     * Deadlocks / lock-wait timeouts (1213 / 1205) are retried by Laravel's own
     * transaction attempts (the `, 3`). A duplicate-key is NOT a concurrency error
     * to Laravel, so we catch it here and re-run the closure — nextNumber() then
     * reads the now-committed row and mints the next value. The whole closure
     * (invoice + line items + stock + payment) rolls back between attempts, so
     * retries stay atomic and side-effect-clean.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public static function withUniqueNumber(callable $callback, int $maxAttempts = 5)
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($callback, 3);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $maxAttempts || ! static::isInvoiceNumberCollision($e)) {
                    throw $e;
                }
                // Another caller took our number; brief jittered back-off, then re-derive.
                usleep(random_int(2000, 15000));
            }
        }
    }

    /**
     * Only retry violations of the invoice_number index — never mask an unrelated
     * unique constraint by blindly re-running the transaction against it.
     */
    protected static function isInvoiceNumberCollision(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), 'invoice_number');
    }
}

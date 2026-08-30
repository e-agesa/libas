<?php

namespace App\Models;

use App\Support\StockLedger;
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
        'stock_restored_at',
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
            'stock_restored_at' => 'datetime',
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

    /**
     * Give this invoice's stock back to the shelf.
     *
     * Selling a shelf item takes it out of stock; cancelling that sale has to
     * put it back, or the shop reorders against a figure short by everything
     * it ever voided. This runs once per invoice — stock_restored_at is the
     * guard, so voiding and then deleting cannot return the same units twice —
     * and it writes a return movement per product, so the change shows up in
     * the stock history instead of appearing from nowhere.
     *
     * Returns the number of products whose stock moved.
     */
    public function restoreStock(): int
    {
        if ($this->stock_restored_at !== null) {
            return 0;
        }

        $moved = 0;

        DB::transaction(function () use (&$moved) {
            $fresh = static::whereKey($this->getKey())->lockForUpdate()->first();

            // Another request may have restored it while we waited for the lock.
            if (!$fresh || $fresh->stock_restored_at !== null) {
                return;
            }

            $lines = $this->lineItems()
                ->where('item_type', 'collection')
                ->whereNotNull('collection_id')
                ->get();

            foreach ($lines as $line) {
                $collection = Collection::find($line->collection_id);

                // The product may have been deleted since the sale.
                if (!$collection) {
                    continue;
                }

                $qty = (int) round((float) $line->quantity);
                if ($qty <= 0) {
                    continue;
                }

                // Back to the exact variation it left from, not to the product
                // as a whole — otherwise the roll-up looks right while every
                // size underneath it is wrong. Positive quantity = stock coming
                // in, matching how a sale going out is recorded.
                StockLedger::give($collection, $line->collection_variant_id, $qty, [
                    'invoice_id' => $this->id,
                    'reference' => $this->invoice_number,
                    'notes' => "Returned to stock from {$this->invoice_number}: {$collection->name} x{$qty}",
                ]);

                $moved++;
            }

            $fresh->forceFill(['stock_restored_at' => now()])->save();
            $this->stock_restored_at = $fresh->stock_restored_at;
        }, 3);

        return $moved;
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

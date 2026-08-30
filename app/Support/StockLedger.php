<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\CollectionVariant;
use App\Models\StockMovement;

/**
 * The one place stock moves when something is sold or given back.
 *
 * A product with variations does not hold its stock on the product row — each
 * size / colour / design holds its own. Selling "White Topi" is meaningless;
 * what leaves the shelf is "White Topi, 21.5, Design 2". Every till and every
 * invoice goes through here so the right variation is debited, the product's
 * roll-up stays in step with its variations, and the movement is written to the
 * ledger naming the exact variation — instead of each screen reimplementing it
 * slightly differently and drifting apart.
 */
class StockLedger
{
    /**
     * Take stock off the shelf for a sale.
     *
     * The decrement is guarded (WHERE stock_qty >= qty), so two tills selling
     * the last unit at the same moment cannot both succeed.
     *
     * @return bool false when there was not enough stock — the caller should
     *              abort the sale rather than continue.
     */
    public static function take(Collection $collection, ?int $variantId, int $qty, array $movement = []): bool
    {
        if ($qty <= 0) {
            return true;
        }

        $variant = self::variantFor($collection, $variantId);

        if ($variant) {
            $ok = CollectionVariant::whereKey($variant->id)
                ->where('stock_qty', '>=', $qty)
                ->decrement('stock_qty', $qty);

            if (! $ok) {
                return false;
            }

            $collection->recalcStockFromVariants();
        } elseif (self::hasVariants($collection)) {
            // Nobody said which variation — an older line item, or a screen
            // that does not offer them yet. The units still have to come off
            // the variations, because the product's own figure is recomputed
            // from them: decrementing the product alone would silently undo
            // itself the next time anyone edited a variation.
            if (! self::drawDown($collection, $qty)) {
                return false;
            }

            $collection->recalcStockFromVariants();
        } else {
            $ok = Collection::whereKey($collection->id)
                ->where('stock_qty', '>=', $qty)
                ->decrement('stock_qty', $qty);

            if (! $ok) {
                return false;
            }

            $collection->refresh();
            $collection->syncSingleVariantStock();
        }

        $collection->refresh();
        StockMovement::record($collection, 'sale', -$qty, self::annotate($movement, $variant));

        return true;
    }

    /**
     * Put stock back — a voided sale, a deleted invoice, a return.
     *
     * Unlike take() this never refuses: stock coming back has no ceiling.
     */
    public static function give(Collection $collection, ?int $variantId, int $qty, array $movement = []): void
    {
        if ($qty <= 0) {
            return;
        }

        $variant = self::variantFor($collection, $variantId);

        if ($variant) {
            CollectionVariant::whereKey($variant->id)->increment('stock_qty', $qty);
            $collection->recalcStockFromVariants();
        } elseif ($first = self::firstVariant($collection)) {
            // An older sale that never recorded which variation. Putting it back
            // on the product alone would vanish at the next roll-up, so it goes
            // to the first variation — arguable, but the count stays honest.
            CollectionVariant::whereKey($first->id)->increment('stock_qty', $qty);
            $collection->recalcStockFromVariants();
        } else {
            $collection->increment('stock_qty', $qty);
            $collection->refresh();
            $collection->syncSingleVariantStock();
        }

        $collection->refresh();
        StockMovement::record($collection, 'return', $qty, self::annotate($movement, $variant));
    }

    /**
     * How much of this product (or this variation of it) can still be sold.
     */
    public static function available(Collection $collection, ?int $variantId): int
    {
        $variant = self::variantFor($collection, $variantId);

        if ($variant) {
            return (int) $variant->stock_qty;
        }

        // No variation named: what is sellable is the total across them all.
        return self::hasVariants($collection)
            ? (int) $collection->variants()->sum('stock_qty')
            : (int) $collection->stock_qty;
    }

    /**
     * What to call this line in an error message: "White Topi (21.5 · White)".
     */
    public static function describe(Collection $collection, ?int $variantId): string
    {
        $variant = self::variantFor($collection, $variantId);

        return $variant ? "{$collection->name} ({$variant->label})" : $collection->name;
    }

    protected static function hasVariants(Collection $collection): bool
    {
        return $collection->variants()->exists();
    }

    protected static function firstVariant(Collection $collection): ?CollectionVariant
    {
        return $collection->variants()->orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Take a quantity off a product's variations, oldest listed first.
     *
     * Only used when the caller could not say which variation — it keeps the
     * variations and the product's total agreeing. Refuses outright if the
     * product does not hold enough across all of them, rather than driving any
     * one of them negative.
     */
    protected static function drawDown(Collection $collection, int $qty): bool
    {
        // Locked for the life of the transaction. Without this the sum below is
        // read outside any guard, so two callers could both find enough stock
        // and both take it — the very oversell the named-variation path was
        // written to prevent. The web shop always lands here, because its pages
        // do not offer variations to choose from.
        $variants = $collection->variants()
            ->orderBy('sort_order')->orderBy('id')
            ->lockForUpdate()->get();

        if ($variants->sum('stock_qty') < $qty) {
            return false;
        }

        $left = $qty;

        foreach ($variants as $v) {
            if ($left <= 0) {
                break;
            }

            $take = min($left, (int) $v->stock_qty);

            if ($take <= 0) {
                continue;
            }

            // Guarded, like every other decrement here, so the lock is a
            // performance nicety rather than the only thing standing between
            // this and negative stock.
            $taken = CollectionVariant::whereKey($v->id)
                ->where('stock_qty', '>=', $take)
                ->decrement('stock_qty', $take);

            if (! $taken) {
                return false;
            }

            $left -= $take;
        }

        return $left <= 0;
    }

    /**
     * Only ever accept a variation that really belongs to this product — a
     * forged id must not be able to drain another product's stock.
     */
    protected static function variantFor(Collection $collection, ?int $variantId): ?CollectionVariant
    {
        if (! $variantId) {
            return null;
        }

        return CollectionVariant::whereKey($variantId)
            ->where('collection_id', $collection->id)
            ->first();
    }

    /**
     * The ledger is read by people, so name the variation in the note.
     */
    protected static function annotate(array $movement, ?CollectionVariant $variant): array
    {
        if ($variant && ! empty($movement['notes'])) {
            $movement['notes'] = rtrim($movement['notes']) . " [{$variant->label}]";
        }

        return $movement;
    }
}

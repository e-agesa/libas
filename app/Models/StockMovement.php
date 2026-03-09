<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'movable_type',
        'movable_id',
        'type',
        'quantity',
        'balance_after',
        'unit_cost',
        'reference',
        'invoice_id',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
        ];
    }

    public function movable()
    {
        return $this->morphTo();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Model $item, string $type, int $quantity, array $extra = []): self
    {
        $balanceAfter = $item->stock_qty;

        return self::create(array_merge([
            'movable_type' => get_class($item),
            'movable_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'user_id' => auth()->id(),
        ], $extra));
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'purchase' => 'green',
            'sale' => 'red',
            'reservation' => 'amber',
            'release' => 'blue',
            'adjustment' => 'gray',
            'return' => 'teal',
            'waste' => 'orange',
            default => 'gray',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'purchase' => 'pi-plus-circle',
            'sale' => 'pi-minus-circle',
            'reservation' => 'pi-lock',
            'release' => 'pi-lock-open',
            'adjustment' => 'pi-sliders-h',
            'return' => 'pi-replay',
            'waste' => 'pi-trash',
            default => 'pi-circle',
        };
    }
}

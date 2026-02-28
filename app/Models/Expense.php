<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'amount',
        'date',
        'paid_to',
        'payment_method',
        'reference',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function categories(): array
    {
        return [
            'Rent',
            'Utilities',
            'Salary',
            'Materials',
            'Equipment',
            'Transport',
            'Marketing',
            'Maintenance',
            'Tax',
            'Other',
        ];
    }
}

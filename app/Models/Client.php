<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'alt_phone',
        'email',
        'address',
        'type',
        'notes',
        'status',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function getTotalSpentAttribute()
    {
        return $this->invoices()->where('status', '!=', 'voided')->sum('total');
    }
}

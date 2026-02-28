<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'relationship',
        'phone',
        'gender',
        'age_group',
        'notes',
        'photo',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function measurements()
    {
        return $this->hasMany(Measurement::class);
    }

    public function invoiceLineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}

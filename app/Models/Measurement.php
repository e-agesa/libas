<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'garment_type',
        'label',
        'date_taken',
        'unit',
        'values',
        'measured_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'date_taken' => 'date',
        ];
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function measuredByUser()
    {
        return $this->belongsTo(User::class, 'measured_by');
    }

    public function invoiceLineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function getGarmentColorAttribute(): string
    {
        $type = \App\Models\GarmentType::where('slug', $this->garment_type)->first();
        return $type?->color ?? '#6b7280';
    }
}

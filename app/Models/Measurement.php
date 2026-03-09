<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'parent_id',
        'revision',
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

    /**
     * The original (first) measurement in this revision series.
     * Null if this IS the original.
     */
    public function parent()
    {
        return $this->belongsTo(Measurement::class, 'parent_id');
    }

    /**
     * All subsequent revisions of this measurement.
     * Only meaningful on the original (parent_id = null).
     */
    public function revisions()
    {
        return $this->hasMany(Measurement::class, 'parent_id')->orderBy('revision');
    }

    /**
     * Get the root measurement of this revision series.
     */
    public function getSeriesRootAttribute(): self
    {
        return $this->parent_id ? ($this->parent ?? $this) : $this;
    }

    /**
     * Get all measurements in the same revision series (including self).
     */
    public function getSeriesAttribute()
    {
        $rootId = $this->parent_id ?? $this->id;

        return static::where(function ($q) use ($rootId) {
            $q->where('id', $rootId)->orWhere('parent_id', $rootId);
        })->orderBy('revision')->get();
    }

    public function getGarmentColorAttribute(): string
    {
        $type = \App\Models\GarmentType::where('slug', $this->garment_type)->first();
        return $type?->color ?? '#6b7280';
    }
}

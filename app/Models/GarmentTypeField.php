<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GarmentTypeField extends Model
{
    use HasFactory;

    protected $fillable = [
        'garment_type_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function garmentType()
    {
        return $this->belongsTo(GarmentType::class);
    }

    public static function generateSlug(string $name): string
    {
        return Str::slug($name, '_');
    }
}

<?php

namespace Database\Seeders;

use App\Models\GarmentType;
use Illuminate\Database\Seeder;

class GarmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Kanzu',
                'slug' => 'kanzu',
                'color' => '#22c55e',
                'sort_order' => 1,
                'fields' => ['Shoulder', 'Chest', 'Sleeve Length', 'Sleeve Width', 'Collar', 'Kanzu Length', 'Body Width', 'Cuff'],
            ],
            [
                'name' => 'Shirt',
                'slug' => 'shirt',
                'color' => '#3b82f6',
                'sort_order' => 2,
                'fields' => ['Shoulder', 'Chest', 'Sleeve Length', 'Sleeve Width', 'Collar', 'Shirt Length', 'Body Width', 'Cuff'],
            ],
            [
                'name' => 'Trouser',
                'slug' => 'trouser',
                'color' => '#a16207',
                'sort_order' => 3,
                'fields' => ['Waist', 'Hips', 'Trouser Length', 'Thigh', 'Knee', 'Bottom', 'Crotch'],
            ],
            [
                'name' => 'Vest',
                'slug' => 'vest',
                'color' => '#8b5cf6',
                'sort_order' => 4,
                'fields' => ['Shoulder', 'Chest', 'Vest Length', 'Body Width'],
            ],
        ];

        foreach ($types as $typeData) {
            $fields = $typeData['fields'];
            unset($typeData['fields']);

            $garmentType = GarmentType::firstOrCreate(
                ['slug' => $typeData['slug']],
                $typeData,
            );

            foreach ($fields as $index => $fieldName) {
                $garmentType->fields()->firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($fieldName, '_')],
                    [
                        'name' => $fieldName,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }
}

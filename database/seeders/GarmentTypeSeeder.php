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
                'fields' => ['Shoulder', 'Chest', 'Sleeve Length', 'Sleeve Width', 'Collar', 'Kanzu Length', 'Body Width', 'Cuff', 'Arm Hole', 'Neck', 'Embroidery Style'],
            ],
            [
                'name' => 'Shirt',
                'slug' => 'shirt',
                'color' => '#3b82f6',
                'sort_order' => 2,
                'fields' => ['Shoulder', 'Chest', 'Sleeve Length', 'Sleeve Width', 'Collar', 'Shirt Length', 'Body Width', 'Cuff', 'Neck', 'Collar Type'],
            ],
            [
                'name' => 'Trouser',
                'slug' => 'trouser',
                'color' => '#a16207',
                'sort_order' => 3,
                'fields' => ['Waist', 'Hips', 'Trouser Length', 'Thigh', 'Knee', 'Bottom', 'Crotch', 'Inseam', 'Rise', 'Trouser Style'],
            ],
            [
                'name' => 'Vest',
                'slug' => 'vest',
                'color' => '#8b5cf6',
                'sort_order' => 4,
                'fields' => ['Shoulder', 'Chest', 'Vest Length', 'Body Width', 'Neck', 'Button Count'],
            ],
            [
                'name' => 'Suit Jacket',
                'slug' => 'suit_jacket',
                'color' => '#6366f1',
                'sort_order' => 5,
                'fields' => ['Chest', 'Shoulder', 'Sleeve Length', 'Arm Hole', 'Wrist', 'Jacket Length', 'Neck', 'Lapel Style', 'Button Count'],
            ],
            [
                'name' => 'Bui-Bui / Abaya',
                'slug' => 'bui_bui',
                'color' => '#ec4899',
                'sort_order' => 6,
                'fields' => ['Chest', 'Shoulder', 'Sleeve Length', 'Arm Hole', 'Garment Length', 'Hips', 'Embroidery Style', 'Niqab'],
            ],
            [
                'name' => 'Dress',
                'slug' => 'dress',
                'color' => '#f43f5e',
                'sort_order' => 7,
                'fields' => ['Bust', 'Waist', 'Hips', 'Shoulder', 'Sleeve Length', 'Dress Length', 'Neckline Style', 'Skirt Style'],
            ],
            [
                'name' => 'Kofia / Cap',
                'slug' => 'kofia',
                'color' => '#14b8a6',
                'sort_order' => 8,
                'fields' => ['Head Circumference', 'Height', 'Style', 'Embroidery Pattern'],
            ],
        ];

        foreach ($types as $typeData) {
            $fields = $typeData['fields'];
            unset($typeData['fields']);

            $garmentType = GarmentType::firstOrCreate(
                ['slug' => $typeData['slug']],
                $typeData,
            );

            // Also update name/color/sort if it already existed
            $garmentType->update($typeData);

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

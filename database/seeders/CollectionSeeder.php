<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\CollectionCategory;
use Illuminate\Database\Seeder;

class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        $kanzus = CollectionCategory::create(['name' => 'Ready-made Kanzus', 'description' => 'Pre-made kanzu garments']);
        $caps = CollectionCategory::create(['name' => 'Caps & Kofias', 'description' => 'Traditional caps and kofias']);
        $accessories = CollectionCategory::create(['name' => 'Accessories', 'description' => 'Buttons, cufflinks, and more']);

        $items = [
            ['category_id' => $kanzus->id, 'name' => 'White Kanzu — Standard', 'sku' => 'KNZ-WHT-STD', 'size' => 'L', 'color' => 'White', 'price' => 3500, 'cost_price' => 2000, 'stock_qty' => 12],
            ['category_id' => $kanzus->id, 'name' => 'White Kanzu — Premium', 'sku' => 'KNZ-WHT-PRM', 'size' => 'XL', 'color' => 'White', 'price' => 5500, 'cost_price' => 3200, 'stock_qty' => 8],
            ['category_id' => $kanzus->id, 'name' => 'Cream Kanzu — Standard', 'sku' => 'KNZ-CRM-STD', 'size' => 'M', 'color' => 'Cream', 'price' => 3500, 'cost_price' => 2000, 'stock_qty' => 6],
            ['category_id' => $caps->id, 'name' => 'Embroidered Kofia', 'sku' => 'KOF-EMB-001', 'size' => null, 'color' => 'White/Gold', 'price' => 1200, 'cost_price' => 600, 'stock_qty' => 20],
            ['category_id' => $caps->id, 'name' => 'Plain Kofia', 'sku' => 'KOF-PLN-001', 'size' => null, 'color' => 'White', 'price' => 500, 'cost_price' => 200, 'stock_qty' => 35],
            ['category_id' => $accessories->id, 'name' => 'Gold Cufflinks Set', 'sku' => 'ACC-CLK-GLD', 'size' => null, 'color' => 'Gold', 'price' => 800, 'cost_price' => 350, 'stock_qty' => 15],
            ['category_id' => $accessories->id, 'name' => 'Kanzu Button Pack (6)', 'sku' => 'ACC-BTN-006', 'size' => null, 'color' => 'Assorted', 'price' => 300, 'cost_price' => 100, 'stock_qty' => 50],
        ];

        foreach ($items as $item) {
            Collection::create($item);
        }
    }
}

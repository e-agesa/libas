<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->bothify('??-###')),
            'price' => fake()->numberBetween(500, 10000),
            'cost_price' => fake()->numberBetween(200, 5000),
            'stock_qty' => fake()->numberBetween(0, 50),
            'low_stock_threshold' => 5,
            'status' => 'active',
        ];
    }
}

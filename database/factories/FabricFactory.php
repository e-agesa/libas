<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FabricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['Cotton', 'Silk', 'Linen', 'Polyester']),
            'color' => fake()->safeColorName(),
            'price_per_unit' => fake()->numberBetween(200, 2000),
            'stock_qty' => fake()->numberBetween(10, 200),
            'status' => 'active',
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GarmentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'color' => fake()->hexColor(),
            'base_price' => fake()->numberBetween(1000, 5000),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'is_active' => true,
        ];
    }
}

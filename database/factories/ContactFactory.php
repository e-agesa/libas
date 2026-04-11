<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'relationship' => 'self',
            'phone' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['male', 'female']),
        ];
    }
}

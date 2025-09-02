<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class OfficeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'address' => fake()->streetAddress(),
            'number' => fake()->buildingNumber(),
            'zip_code' => fake()->postcode(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_legal' => fake()->boolean(),
            'is_operative' => fake()->boolean(),
            'company_id' => fake()->numberBetween(2, 4),
        ];
    }
}

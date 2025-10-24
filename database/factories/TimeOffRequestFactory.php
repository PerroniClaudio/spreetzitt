<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeOffRequest>
 */
class TimeOffRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(1, 12),
            'company_id' => fake()->numberBetween(2, 3),
            'time_off_type_id' => fake()->numberBetween(1, 5),
            'date_from' => fake()->dateTimeBetween('-3 month', 'today'),
            'date_to' => fake()->dateTimeBetween('-3 month', 'today'),
            'status' => fake()->randomElement([0, 1, 2]),

        ];
    }
}

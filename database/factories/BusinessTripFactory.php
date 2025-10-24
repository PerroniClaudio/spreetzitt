<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessTrip>
 */
class BusinessTripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $date = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => 12,
            'date_from' => $date,
            'date_to' => $date,
            'status' => 0,
            'expense_type' => 0,
        ];
    }
}

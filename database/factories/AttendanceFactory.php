<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 12,
            'company_id' => 3,
            'date' => fake()->unique()->dateTimeBetween('-3 month', 'today'),
            'time_in' => '08:00',
            'time_out' => '12:00',
            'hours' => 4,
        ];
    }
}

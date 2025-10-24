<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketTypeCategory>
 */
class TicketTypeCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $boolean = fake()->boolean();

        return [
            'name' => fake()->word(),
            'is_problem' => $boolean,
            'is_request' => ! $boolean,
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TypeFormFieldsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'field_name' => fake()->word(),
            'field_type' => fake()->randomElement([
                "text",
                "textarea",
                "email",
                "number",
                "date",
                "tel",
                "url"
            ]),
            'field_label' => fake()->word(),
            'required' => fake()->boolean(),
            'description' => fake()->sentence(),
            'placeholder' => fake()->word(),
            'default_value' => fake()->word(),
            'validation' => "required",
            'validation_message' => fake()->sentence(),
            'help_text' => fake()->sentence(),
            'order' => 0
        ];
    }
}

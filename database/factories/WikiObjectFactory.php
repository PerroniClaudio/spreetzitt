<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WikiObject>
 */
class WikiObjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $type = 'file';
        $file_name = $this->faker->word;

        return [
            //
            'name' => $file_name,
            'uploaded_name' => $file_name,
            'mime_type' => $type == 'file' ? $this->faker->randomElement(['application/msword', 'application/vnd.ms-excel', 'application/pdf', 'application/zip', 'application/vnd.ms-powerpoint']) : 'folder',
            'type' => $type,
            'path' => $this->faker->word,
            'is_public' => $this->faker->boolean,
            'uploaded_by' => User::inRandomOrder()->first(),
            'file_size' => $type == 'file' ? $this->faker->randomNumber() : null,
            'company_id' => null,
        ];
    }
}

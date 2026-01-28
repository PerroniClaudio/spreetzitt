<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractAttachment>
 */
class ContractAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'docx', 'jpg', 'png']);
        $filename = fake()->words(3, true).'.'.$extension;

        return [
            'contract_id' => Contract::factory(),
            'file_path' => 'contracts/'.fake()->uuid().'/'.$filename,
            'original_filename' => $filename,
            'display_name' => fake()->optional()->words(3, true),
            'file_extension' => $extension,
            'mime_type' => match ($extension) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                default => 'application/octet-stream',
            },
            'file_size' => fake()->numberBetween(1024, 5242880),
            'uploaded_by' => User::factory(),
        ];
    }
}

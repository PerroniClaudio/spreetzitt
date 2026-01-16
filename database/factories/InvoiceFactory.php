<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InvoicePaymentStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'description' => fake()->optional()->sentence(),
            'company_id' => Company::factory(),
            'payment_stage_id' => InvoicePaymentStage::factory(),
            'invoice_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Invoice without company.
     */
    public function withoutCompany(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => null,
        ]);
    }
}

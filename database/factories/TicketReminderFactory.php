<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketReminder>
 */
class TicketReminderFactory extends Factory
{
    protected $model = TicketReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_uuid' => Str::uuid(),
            'user_id' => User::factory(),
            'ticket_id' => Ticket::factory(),
            'message' => fake()->sentence(),
            'reminder_date' => fake()->dateTimeBetween('now', '+1 month'),
            'is_ticket_deadline' => fake()->boolean(20), // 20% di possibilità che sia una scadenza
        ];
    }

    /**
     * Indicate that the reminder is for a future date.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_date' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }

    /**
     * Indicate that the reminder is for a past date.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the reminder is a ticket deadline.
     */
    public function deadline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ticket_deadline' => true,
            'message' => 'Scadenza del ticket - controllare urgentemente',
        ]);
    }
}

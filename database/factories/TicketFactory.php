<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(9, 11),
            'company_id' => fake()->numberBetween(2, 4),
            'status' => 0, // Vecchio status, da mantenere finchè non si migra definitivamente al nuovo sistema con stage
            'stage_id' => fake()->randomElement(\App\Models\TicketStage::whereIsNull('deleted_at')->pluck('id')->toArray()),
            'type_id' => fake()->numberBetween(1, 10),
            'description' => fake()->sentence(),
            'duration' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Ticket $ticket) {
            // ...
        })->afterCreating(function (Ticket $ticket) {

            $form_fields = $ticket->ticketType->typeFormField;

            $message_data = [];
            $message_data['description'] = $ticket->description;
            foreach ($form_fields as $field) {
                // field_type: email, text, date, radio, tel, select
                $field_type = $field->field_type;
                $value = $field_type == 'date' ? fake()->date() :
                    ($field_type == 'email' ? fake()->email() :
                    ($field_type == 'tel' ? fake()->phoneNumber() :
                    ($field_type == 'radio' || $field_type == 'select' ? $field->field_options[0] :
                    fake()->sentence())));

                $message_data[$field->field_name] = $value;
            }

            // Webform
            TicketMessage::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'message' => json_encode($message_data),
            ]);

            // Messaggio descrizione
            TicketMessage::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'message' => $ticket->description,
            ]);
        });
    }
}

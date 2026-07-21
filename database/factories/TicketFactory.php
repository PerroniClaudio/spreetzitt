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
        $user = \App\Models\User::where('is_admin', false)->inRandomOrder()->first();
        $company = $user ? $user->companies()->first() : \App\Models\Company::inRandomOrder()->first();
        $ticketType = $company ? $company->ticketTypes()->inRandomOrder()->first() : \App\Models\TicketType::inRandomOrder()->first();
        $stage = \App\Models\TicketStage::whereNull('deleted_at')->inRandomOrder()->first();

        // sla_take e sla_solve: TicketType > Company > fallback 60
        $sla_take = $ticketType && $ticketType->default_sla_take ? $ticketType->default_sla_take : ($company && $company->sla_take_low ? $company->sla_take_low : 60);
        $sla_solve = $ticketType && $ticketType->default_sla_solve ? $ticketType->default_sla_solve : ($company && $company->sla_solve_low ? $company->sla_solve_low : 60);
        $priority = $ticketType && $ticketType->default_priority ? $ticketType->default_priority : 1;

        $group_id = null;
        if ($ticketType && method_exists($ticketType, 'groups')) {
            $group = $ticketType->groups()->inRandomOrder()->first();
            $group_id = $group ? $group->id : null;
        }

        return [
            'user_id' => $user ? $user->id : fake()->numberBetween(1, 10),
            'company_id' => $company ? $company->id : fake()->numberBetween(1, 5),
            'status' => 0,
            'stage_id' => $stage ? $stage->id : fake()->numberBetween(1, 10),
            'type_id' => $ticketType ? $ticketType->id : fake()->numberBetween(1, 10),
            'group_id' => $group_id ?? fake()->numberBetween(1, 5),
            'description' => 'Questo ticket è stato generato con TicketFactory. '.fake()->sentence(),
            'duration' => 0,
            'sla_take' => $sla_take,
            'sla_solve' => $sla_solve,
            'priority' => $priority,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Ticket $ticket) {
            // ...
        })->afterCreating(function (Ticket $ticket) {
            $form_fields = $ticket->ticketType ? $ticket->ticketType->typeFormField : [];
            $message_data = [];
            $message_data['description'] = $ticket->description;
            $company_id = $ticket->company_id;
            foreach ($form_fields as $field) {
                $field_type = $field->field_type;
                if ($field_type === 'hardware') {
                    $hardware_ids = \App\Models\Hardware::where('company_id', $company_id)->pluck('id')->toArray();
                    $message_data[$field->field_name] = ! empty($hardware_ids)
                        ? [fake()->randomElement($hardware_ids)]
                        : [fake()->numberBetween(1, 10)];
                } elseif ($field_type === 'date') {
                    $message_data[$field->field_name] = fake()->date();
                } elseif ($field_type === 'email') {
                    $message_data[$field->field_name] = fake()->email();
                } elseif ($field_type === 'tel') {
                    $message_data[$field->field_name] = fake()->phoneNumber();
                } elseif ($field_type === 'radio' || $field_type === 'select') {
                    $options = is_array($field->field_options) ? $field->field_options : [];
                    $message_data[$field->field_name] = ! empty($options)
                        ? fake()->randomElement($options)
                        : fake()->word();
                } else {
                    $message_data[$field->field_name] = fake()->sentence();
                }
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

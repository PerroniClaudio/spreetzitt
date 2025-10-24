<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistoryRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketAssignmentHistoryRecordFactory extends Factory
{
    protected $model = TicketAssignmentHistoryRecord::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'admin_user_id' => User::factory(),
            'group_id' => Group::factory(),
            'message' => $this->faker->optional()->sentence(),
        ];
    }
}

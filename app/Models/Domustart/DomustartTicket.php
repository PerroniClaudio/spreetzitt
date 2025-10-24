<?php

namespace App\Models\Domustart;

use App\Models\Ticket;

class DomustartTicket extends Ticket
{
    protected $table = 'tickets';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->mergeFillable([
            'is_visible_all_users',
            'is_visible_admin',
        ]);
    }
}

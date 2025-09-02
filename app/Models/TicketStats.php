<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketStats extends Model {
    use HasFactory;

    protected $fillable = [
        'incident_open',
        'incident_in_progress',
        'incident_waiting',
        'incident_out_of_sla',
        'request_open',
        'request_in_progress',
        'request_waiting',
        'request_out_of_sla',
        'compnanies_opened_tickets'
    ];
}

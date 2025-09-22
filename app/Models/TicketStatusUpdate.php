<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'content',
        'type',
        'show_to_user',
        'old_stage_id',
        'new_stage_id'
    ];

    /* get the owner */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* get the ticket */

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    
}

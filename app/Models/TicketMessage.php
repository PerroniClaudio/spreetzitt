<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class TicketMessage extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachment',
    ];

    public function toSearchableArray()
    {
        return [
            'message' => $this->message,
        ];
    }

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

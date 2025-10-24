<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class OldTicket extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'old_ticket_id',
        'business_name',
        'opened_by',
        'ticket_type',
        'opened_at',
        'closed_at',
        'closing_notes',
    ];

    public function messages()
    {
        return $this->hasMany(OldTicketMessage::class, 'old_ticket_id', 'old_ticket_id');
    }
}

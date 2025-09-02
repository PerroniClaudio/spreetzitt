<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class OldTicketMessage extends Model {
    use HasFactory, Searchable;

    protected $fillable = [
        'old_ticket_id',
        'sender',
        'message',
        'sent_at',
        'is_admin',
    ];

    public function ticket() {
        return $this->belongsTo(OldTicket::class, 'old_ticket_id', 'old_ticket_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_uuid',
        'user_id',
        'ticket_id',
        'message',
        'reminder_date',
        'is_ticket_deadline',
    ];

    protected $casts = [
        'reminder_date' => 'datetime',
        'is_ticket_deadline' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}

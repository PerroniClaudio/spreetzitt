<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'filename',
        'path',
        'extension',
        'mime_type',
        'size',
        'is_deleted',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}

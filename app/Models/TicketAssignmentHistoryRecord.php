<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAssignmentHistoryRecord extends Model
{
    use HasFactory;

    protected $table = 'ticket_assignment_history_records';

    protected $fillable = [
        'ticket_id',
        'admin_user_id',
        'group_id',
        'message',
    ];

    /**
     * The ticket related to this history record.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * The admin user who handled the assignment.
     */
    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * The group related to the assignment.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}

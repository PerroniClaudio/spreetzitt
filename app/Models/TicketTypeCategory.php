<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketTypeCategory extends Model {
    use HasFactory;

    protected $fillable = [
        'name',
        'is_problem',
        'is_request',
        'is_deleted',
    ];

    public function ticketTypes() {
        return $this->hasMany(TicketType::class, 'ticket_type_category_id');
    }
}

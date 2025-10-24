<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomUserGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_id',
        'created_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_custom_groups', 'custom_user_group_id', 'user_id');
    }

    public function ticketTypes()
    {
        return $this->belongsToMany(TicketType::class, 'ticket_types_custom_groups', 'custom_user_group_id', 'ticket_type_id');
    }
}

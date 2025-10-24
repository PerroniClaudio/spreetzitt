<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketProFormaBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_path',
        'start_date',
        'end_date',
        'optional_parameters',
        'is_generated',
        'is_failed',
        'error_message',
        'is_approved',
        'company_id',
        'user_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

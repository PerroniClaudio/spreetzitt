<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeOffRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'time_off_type_id',
        'date_from',
        'date_to',
        'status',
        'batch_id'
    ];

    public function type() { 
        return $this->belongsTo(TimeOffType::class, 'time_off_type_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}

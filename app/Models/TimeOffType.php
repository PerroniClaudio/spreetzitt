<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeOffType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function timeOffRequest() {
        return $this->hasMany(TimeOffRequest::class, 'time_off_type_id');
    }
}

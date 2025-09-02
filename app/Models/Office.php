<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'number',
        'zip_code',
        'city',
        'province',
        'latitude',
        'longitude',
        'is_legal',
        'is_operative',
        'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'section',
        'sheet',
        'parcel',
        'users_number',
        'energy_class',
        'square_meters',
        'thousandths',
        'activity_type',
        'in_use_by',
        'company_id',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'properties_users');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

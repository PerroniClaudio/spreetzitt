<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessTripTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_trip_id',
        'company_id',
        'date',
        'address',
        'city',
        'province',
        'zip_code',
        'latitude',
        'longitude',
    ];

    /**
     * Get the business trip that owns the business trip transfer.
     */

    public function businessTrip() {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

}

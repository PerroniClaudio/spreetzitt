<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessTripExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_trip_id',
        'company_id',
        'payment_type',
        'expense_type',
        'amount',
        'date',
        'address',
        'city',
        'province',
        'zip_code',
        'latitude',
        'longitude',
    ];

    /**
     * Get the business trip that owns the business trip expense.
     */
    public function businessTrip()
    {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

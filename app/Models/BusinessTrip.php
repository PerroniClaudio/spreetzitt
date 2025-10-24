<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date_from',
        'date_to',
        'status',
        'expense_type',
    ];

    /**
     * Get the user that owns the business trip.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expenses()
    {
        return $this->hasMany(BusinessTripExpense::class);
    }

    public function transfers()
    {
        return $this->hasMany(BusinessTripTransfer::class);
    }
}

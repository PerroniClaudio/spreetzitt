<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        "uid",
        "token",
        "used",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

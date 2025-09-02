<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'configuration',
        'enabled_widgets',
    ];

    protected $casts = [
        'configuration' => 'array',
        'enabled_widgets' => 'array',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}

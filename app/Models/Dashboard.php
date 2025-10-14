<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'configuration',
        'enabled_widgets',
        'settings',
    ];

    protected $casts = [
        'configuration' => 'array',
        'enabled_widgets' => 'array',
        'settings' => 'array',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedLoginAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'user_id',
        'ip_address',
        'user_agent',
        'attempt_type',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Conta i tentativi falliti per una email nelle ultime 24 ore
     */
    public static function countRecentFailedAttempts(string $email): int
    {
        return static::where('email', $email)
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Ottiene gli ultimi N tentativi falliti per una email
     */
    public static function getRecentFailedAttempts(string $email, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('email', $email)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}

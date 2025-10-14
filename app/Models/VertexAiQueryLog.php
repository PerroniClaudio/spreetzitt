<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VertexAiQueryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_email',
        'user_prompt',
        'generated_sql',
        'ai_response',
        'result_count',
        'was_successful',
        'error_message',
        'ip_address',
        'user_agent',
        'execution_time',
    ];

    protected $casts = [
        'was_successful' => 'boolean',
        'execution_time' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

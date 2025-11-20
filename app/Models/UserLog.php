<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'modified_by',
        'user_id',
        'old_data',
        'new_data',
        'log_subject',
        'log_type',
    ];

    /**
     * Get the user who performed the action.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    /**
     * Get the user who was affected by the action.
     */
    public function affectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the old data as an array.
     */
    public function oldData()
    {
        return json_decode($this->old_data, true);
    }

    /**
     * Get the new data as an array.
     */
    public function newData()
    {
        return json_decode($this->new_data, true);
    }
}

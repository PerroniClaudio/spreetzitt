<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'status_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the status/stage of the contract.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ContractStage::class, 'status_id');
    }

    /**
     * Get the attachments associated with this contract.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class);
    }

    /**
     * Get the invoices associated with this contract.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class)
            ->withPivot('reference_period_start', 'reference_period_end')
            ->withTimestamps();
    }
}

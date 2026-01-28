<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'admin_color',
    ];

    /**
     * Get the contracts associated with this stage.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'status_id');
    }
}

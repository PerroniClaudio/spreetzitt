<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'description',
        'company_id',
        'payment_stage_id',
        'invoice_date',
    ];

    protected $casts = [
        'invoice_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentStage(): BelongsTo
    {
        return $this->belongsTo(InvoicePaymentStage::class, 'payment_stage_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

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

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Invoice $invoice): void {
            if (! $invoice->isDirty('number') && ! $invoice->isDirty('invoice_date')) {
                return;
            }

            $invoiceNumberExistsForYear = static::withTrashed()
                ->where('number', $invoice->number)
                ->whereYear('invoice_date', $invoice->invoice_date->year)
                ->when($invoice->exists, fn ($query) => $query->whereKeyNot($invoice->getKey()))
                ->exists();

            if ($invoiceNumberExistsForYear) {
                throw ValidationException::withMessages([
                    'number' => 'Il numero fattura è già utilizzato per l’anno di emissione selezionato.',
                ]);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentStage(): BelongsTo
    {
        return $this->belongsTo(InvoicePaymentStage::class, 'payment_stage_id');
    }

    /**
     * Get the contracts associated with this invoice.
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class)
            ->withPivot('reference_period_start', 'reference_period_end')
            ->withTimestamps();
    }

    /**
     * Get the tickets associated with this invoice.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

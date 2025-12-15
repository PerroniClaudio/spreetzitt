<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketReportPdfExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_path',
        'start_date',
        'end_date',
        'optional_parameters',
        'company_id',
        'is_generated',
        'is_user_generated',
        'is_failed',
        'error_message',
        'user_id',
        'is_approved_billing',
        'approved_billing_identification',
        'send_email',
        'is_ai_generated',
        'ai_query',
        'ai_prompt',
    ];

    /**
     * Boot del model per validazioni
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            // Valida che i report non AI abbiano company_id
            if (!$report->is_ai_generated && !$report->company_id) {
                throw new \Exception('company_id è obbligatorio per i report non generati dall\'AI');
            }
        });
    }

    // Crea l'identificativo del report PDF da utilizzare come riferimento in fattura.
    public function generatePdfIdentificationString()
    {
        $company = Company::find($this->company_id);
        if (! $company) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }
        $time = time();
        $hexTime = strtoupper(dechex($time));

        $identificationStringStart = sprintf(
            '%s_PDF_%d_',
            strtoupper(substr($company->name, 0, 3)),
            $company->id
        );
        $identificationString = $identificationStringStart.$hexTime;
        while (self::where('approved_billing_identification', $identificationString)->exists()) {
            $time++;
            $hexTime = strtoupper(dechex($time));
            $identificationString = $identificationStringStart.$hexTime;
        }

        return $identificationString;
    }

    use HasFactory;
}
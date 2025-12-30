<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectReportPdfExport extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'start_date',
        'end_date',
        'optional_parameters',
        'project_id',
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

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($report) {
            // Validazione: se c'è un project_id, il company_id deve coincidere
            if ($report->project_id) {
                $project = Ticket::find($report->project_id);
                if ($project && $project->company_id !== $report->company_id) {
                    throw new \InvalidArgumentException('Company ID mismatch with project company');
                }
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

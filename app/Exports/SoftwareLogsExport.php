<?php

namespace App\Exports;

use App\Models\SoftwareAuditLog;
use Maatwebsite\Excel\Concerns\FromArray;

class SoftwareLogsExport implements FromArray
{
    private $software_id;

    public function __construct($software_id)
    {
        $this->software_id = $software_id;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array
    {
        $softwareId = $this->software_id;
        $actions = config('app.software_audit_log_actions');
        $subjects = config('app.software_audit_log_subjects');

        $logs = SoftwareAuditLog::where('software_id', $softwareId)->orWhere(function ($query) use ($softwareId) {
            $query->whereJsonContains('old_data->id', $softwareId)
                ->orWhereJsonContains('new_data->id', $softwareId);
        })
            ->with('author')
            ->get();

        $logs_data = [];
        $headers = [
            'ID log',
            'ID Autore',
            'Cognome e Nome Autore',
            'Azione',
            'Oggetto della modifica',
            'Data',
            'Dati precedenti',
            'Dati successivi',
        ];

        foreach ($logs as $log) {
            $current_log = [
                $log->id,
                $log->author ? $log->author->id : null,
                $log->author ? ($log->author->surname ? $log->author->surname.' ' : '').$log->author->name : null,
                $actions[$log->log_type] ?? $log->log_type,
                $subjects[$log->log_subject] ?? $log->log_subject,
                $log->created_at,
                $log->old_data,
                $log->new_data,
            ];

            $logs_data[] = $current_log;
        }

        return [
            $headers,
            $logs_data,
        ];
    }
}

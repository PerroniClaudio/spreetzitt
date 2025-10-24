<?php

namespace App\Exports;

use App\Models\HardwareAuditLog;
use Maatwebsite\Excel\Concerns\FromArray;

class HardwareLogsExport implements FromArray
{
    private $hardware_id;

    public function __construct($hardware_id)
    {
        $this->hardware_id = $hardware_id;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array
    {
        $hardwareId = $this->hardware_id;
        $actions = config('app.hardware_audit_log_actions');
        $subjects = config('app.hardware_audit_log_subjects');

        $logs = HardwareAuditLog::where('hardware_id', $hardwareId)->orWhere(function ($query) use ($hardwareId) {
            $query->whereJsonContains('old_data->id', $hardwareId)
                ->orWhereJsonContains('new_data->id', $hardwareId);
        })
            ->with('author')
            ->get();

        $logs_data = [];
        $headers = [
            'ID log',
            'ID Autore',
            'Cognome e Nome Autore',
            'Azione', // referer
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

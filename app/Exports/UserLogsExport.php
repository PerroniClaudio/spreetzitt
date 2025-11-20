<?php

namespace App\Exports;

use App\Models\UserLog;
use Maatwebsite\Excel\Concerns\FromArray;

class UserLogsExport implements FromArray
{
    private $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array
    {
        $userId = $this->user_id;
        $actions = [
            'create' => 'Creazione',
            'update' => 'Modifica',
            'delete' => 'Eliminazione',
        ];
        $subjects = [
            'user' => 'Utente',
            'user_company' => 'Associazione Utente-Azienda',
        ];

        $logs = UserLog::where('user_id', $userId)
            ->orWhere('modified_by', $userId)
            ->with(['author', 'affectedUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        $logs_data = [];
        $headers = [
            'ID log',
            'ID Autore',
            'Cognome e Nome Autore',
            'ID Utente Interessato',
            'Cognome e Nome Utente Interessato',
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
                $log->affectedUser ? $log->affectedUser->id : null,
                $log->affectedUser ? ($log->affectedUser->surname ? $log->affectedUser->surname.' ' : '').$log->affectedUser->name : null,
                $actions[$log->log_type] ?? $log->log_type,
                $subjects[$log->log_subject] ?? $log->log_subject,
                $log->created_at,
                json_encode($log->old_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                json_encode($log->new_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ];

            $logs_data[] = $current_log;
        }

        return [
            $headers,
            ...$logs_data,
        ];
    }
}

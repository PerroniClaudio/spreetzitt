<?php

namespace App\Imports;

use App\Exports\RowsExport;
use App\Http\Controllers\FileUploadController;
use App\Jobs\SendOpenMassiveTicketEmail;
use App\Jobs\SendOpenTicketEmail;
use App\Models\Ticket;
use App\Models\TicketFile;
use App\Models\TicketMessage;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Facades\Excel;

class TicketsImport implements ToCollection
{
    protected $additionalData;

    public function __construct($additionalData)
    {
        $this->additionalData = $additionalData;
    }

    /**
     * @param  array  $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function collection(Collection $rows)
    {
        $mergeRows = $this->additionalData['formData']->merge_rows;
        $setStage = $this->additionalData['formData']->set_stage;

        $user = $this->additionalData['user'];
        $formData = $this->additionalData['formData'];

        $generatedTicketsError = []; // Per il messaggio di errore all'admin

        $generatedTicketsInfo = []; // Per il messaggio informativo all'utente
        // [id => id, text => 'ID ticket, richiesta o problema - tipo di ticket. identificativo']
        $brand_url = null;

        // Gestire qui le righe del file Excel
        try {
            $ticketType = TicketType::find($formData->type_id);
            $group = $ticketType->groups->first();
            $groupId = $group ? $group->id : null;

            $openTicketStageId = TicketStage::where('system_key', 'new')->value('id');

            if ($mergeRows) {

                $distinctValues = [];
                foreach ($rows as $index => $row) {
                    if ($index > 0 && isset($row[0])) {
                        $distinctValues[] = $row[0];
                    }
                }
                $distinctValues = array_unique($distinctValues);

                foreach ($distinctValues as $currentValue) {

                    $formData->messageData->Identificativo = $currentValue;

                    // $ticketType = TicketType::find($formData->type_id);
                    // $group = $ticketType->groups->first();
                    // $groupId = $group ? $group->id : null;

                    $ticket = Ticket::create([
                        'description' => $formData->description,
                        'type_id' => $ticketType->id,
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'status' => '0', // Da mantenere fino a dopo la migrazione del vecchio db
                        'stage_id' => $openTicketStageId,
                        'company_id' => $formData->company,
                        'file' => null,
                        'duration' => 0,
                        'sla_take' => $ticketType['default_sla_take'],
                        'sla_solve' => $ticketType['default_sla_solve'],
                        'priority' => $ticketType['default_priority'],
                        'unread_mess_for_adm' => 0,
                        'unread_mess_for_usr' => 1,
                        'source' => $formData->source ?? null,
                        'is_user_error' => 1, // is_user_error viene usato per la responsabilità del dato e di default è assegnata al cliente.
                        'is_billable' => $ticketType['expected_is_billable'],
                    ]);

                    $generatedTicketsError[] = 'ID ticket: '.$ticket->id.' - Identificativo valore file import: '.$currentValue.' - Tipo di apertura ticket: raggruppato';
                    $generatedTicketsInfo[] = [
                        'id' => $ticket->id,
                        'text' => 'ID ticket: '.$ticket->id.' - Identificativo: '.$currentValue,
                    ];

                    cache()->forget('user_'.$user->id.'_tickets');
                    cache()->forget('user_'.$user->id.'_tickets_with_closed');

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => json_encode($formData->messageData),
                    ]);

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => $formData->description,
                    ]);

                    if (! $brand_url) {
                        $brand_url = $ticket->brandUrl();
                    }
                    // dispatch(new SendOpenTicketEmail($ticket, $brand_url));

                    // Salva il file come allegato del ticket
                    $filteredRows = $rows->filter(function ($row) use ($currentValue) {
                        return isset($row[0]) && $row[0] == $currentValue;
                    });

                    $fileName = '';

                    // Crea il file se ci sono più righe raggruppate
                    if ($filteredRows->isNotEmpty()) {
                        $filteredRows->prepend($rows[0]); // Add the first row to the filtered rows

                        $fileName = 'file_'.$currentValue.'_'.time().substr(uniqid(), -3).'.xlsx';
                    }
                    $path = FileUploadController::storagePathPrefix().$ticket->id.'/';

                    $export = new RowsExport($filteredRows);
                    $disk = FileUploadController::getStorageDisk();
                    Excel::store($export, $path.$fileName, $disk);
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $size = Storage::disk($disk)->size($path.$fileName);
                    $ticketFile = TicketFile::create([
                        'ticket_id' => $ticket->id,
                        'filename' => $fileName,
                        'path' => $path.$fileName,
                        'extension' => $extension,
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'size' => $size,
                    ]);

                    // Modifica dello stato del ticket
                    if ($setStage) {
                        $selectedStage = TicketStage::find($setStage);
                        if ($selectedStage) {
                            $oldStageId = $ticket->stage_id;
                            $ticket->fill([
                                'stage_id' => $selectedStage->id,
                                // 'wait_end' => $setWaitEnd || null,
                            ])->save();
                            $update = TicketStatusUpdate::create([
                                'ticket_id' => $ticket->id,
                                'user_id' => $user->id,
                                'old_stage_id' => $oldStageId,
                                'new_stage_id' => $selectedStage->id,
                                'content' => 'Stato del ticket modificato in "'.$selectedStage->name.'"',
                                'type' => 'status',
                            ]);
                        } else {
                            $generatedTicketsError[] = 'ID ticket: '.$ticket->id.' - Impossibile modificare lo stato del ticket. Lo stage selezionato non esiste più.';
                        }
                        // La mail non la inviamo. Nemmeno una singola per tutti i ticket generati.
                    }
                }

            } else {
                foreach ($rows as $index => $row) {
                    if ($index == 0) {
                        continue;
                    }

                    $currentValue = $row[0];

                    $formData->messageData->Identificativo = $currentValue;

                    // $ticketType = TicketType::find($formData->type_id);
                    // $group = $ticketType->groups->first();
                    // $groupId = $group ? $group->id : null;

                    $ticket = Ticket::create([
                        'description' => $formData->description,
                        'type_id' => $ticketType->id,
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'status' => '0', // Da mantenere fino a dopo la migrazione del vecchio db
                        'stage_id' => $openTicketStageId,
                        'company_id' => $formData->company,
                        'file' => null,
                        'duration' => 0,
                        'sla_take' => $ticketType['default_sla_take'],
                        'sla_solve' => $ticketType['default_sla_solve'],
                        'priority' => $ticketType['default_priority'],
                        'unread_mess_for_adm' => 0,
                        'unread_mess_for_usr' => 1,
                        'source' => $formData->source ?? null,
                        'is_user_error' => 1, // is_user_error viene usato per la responsabilità del dato e di default è assegnata al cliente.
                        'is_billable' => $ticketType['expected_is_billable'],
                    ]);

                    $generatedTicketsError[] = 'ID ticket: '.$ticket->id.' - Identificativo valore file import: '.$currentValue.' - Tipo di apertura ticket: suddiviso - Indice riga: '.$index;
                    $generatedTicketsInfo[] = [
                        'id' => $ticket->id,
                        'text' => 'ID ticket: '.$ticket->id.' - '.($ticketType->category->is_problem ? 'Problema' : 'Richiesta').' - '.$ticketType->name.'. Identificativo: '.$currentValue,
                    ];

                    cache()->forget('user_'.$user->id.'_tickets');
                    cache()->forget('user_'.$user->id.'_tickets_with_closed');

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => json_encode($formData->messageData),
                    ]);

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => $formData->description,
                    ]);

                    if (! $brand_url) {
                        $brand_url = $ticket->brandUrl();
                    }
                    // dispatch(new SendOpenTicketEmail($ticket, $brand_url));

                    // Salva il file come allegato del ticket
                    $filteredRows = collect([$row]);

                    $fileName = '';

                    if ($filteredRows->isNotEmpty()) {
                        $filteredRows->prepend($rows[0]); // Add the first row to the filtered rows

                        $fileName = 'file_'.$currentValue.'_'.time().substr(uniqid(), -3).'.xlsx';
                    }
                    $path = FileUploadController::storagePathPrefix().$ticket->id.'/';

                    $export = new RowsExport($filteredRows);
                    $disk = FileUploadController::getStorageDisk();
                    Excel::store($export, $path.$fileName, $disk);
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $size = Storage::disk($disk)->size($path.$fileName);
                    $ticketFile = TicketFile::create([
                        'ticket_id' => $ticket->id,
                        'filename' => $fileName,
                        'path' => $path.$fileName,
                        'extension' => $extension,
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'size' => $size,
                    ]);

                    // Modifica dello stato del ticket
                    if ($setStage) {
                        $selectedStage = TicketStage::find($setStage);
                        if ($selectedStage) {
                            $oldStageId = $ticket->stage_id;
                            $ticket->fill([
                                'stage_id' => $selectedStage->id,
                                // 'wait_end' => $setWaitEnd || null,
                            ])->save();
                            $update = TicketStatusUpdate::create([
                                'ticket_id' => $ticket->id,
                                'user_id' => $user->id,
                                'old_stage_id' => $oldStageId,
                                'new_stage_id' => $selectedStage->id,
                                'content' => 'Stato del ticket modificato in "'.$selectedStage->name.'"',
                                'type' => 'status',
                            ]);
                        } else {
                            $generatedTicketsError[] = 'ID ticket: '.$ticket->id.' - Impossibile modificare lo stato del ticket. Lo stage selezionato non esiste più.';
                        }
                        // La mail non la inviamo. Nemmeno una singola per tutti i ticket generati.
                    }

                }
            }

            dispatch(new SendOpenMassiveTicketEmail($generatedTicketsInfo, $brand_url));

        } catch (\Exception $e) {
            $mailSubject = 'Errore generazione ticket';
            $mailContent = implode("\n\n", $generatedTicketsError)."\n\n".$e->getMessage();

            // Questo dovrà poi andare nel suo file apposito
            // Send email to user
            Mail::raw($mailContent, function ($message) use ($user, $mailSubject) {
                $message->to($user->email)
                    ->subject($mailSubject);
            });

            throw $e;
        }
    }
}

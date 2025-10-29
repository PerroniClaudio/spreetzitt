<?php

namespace App\Http\Controllers;

use App\Imports\TicketsImport;
use App\Jobs\SendCloseTicketEmail;
use App\Jobs\SendOpenTicketEmail;
use App\Jobs\SendUpdateEmail;
use App\Models\Domustart\DomustartTicket;
use App\Models\Group;
use App\Models\Hardware;
use App\Models\HardwareAuditLog;
use App\Models\Ticket;
use App\Models\TicketAssignmentHistoryRecord;
use App\Models\TicketFile;
use App\Models\TicketMessage;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache; // Otherwise no redis connection :)
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Maatwebsite\Excel\Facades\Excel;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Show only the tickets belonging to the authenticated user (for company users and company_admin. support admin use adminGroupsTickets)

        $user = $request->user();
        // Deve comprendere i ticket chiusi?
        $withClosed = $request->query('with-closed') == 'true' ? true : false;

        $selectedCompanyId = $this->getSelectedCompanyId($user);
        if (! $selectedCompanyId) {
            return response(['message' => 'No company selected'], 400);
        }

        $closedStageId = TicketStage::where('system_key', 'closed')->value('id');

        // Generazione query in base ai parametri della richiesta.
        $query = Ticket::query()->where('company_id', $selectedCompanyId);
        if (! $withClosed) {
            $query->where('stage_id', '!=', $closedStageId);
        }
        if ($user['is_company_admin'] != 1) {
            $query->where(function (Builder $query) use ($user) {
                /**
                 * @disregard Intelephense non rileva il metodo whereIn
                 */
                $query->where('user_id', $user->id)
                    ->orWhere('referer_id', $user->id);
                if ($user->is_company_admin) {
                    /**
                     * @disregard Intelephense non rileva il metodo whereIn
                     */
                    $query->orWhere('referer_it_id', $user->id);
                }
            });
        }
        $query->with([
            'referer' => function ($query) {
                $query->select('id', 'name', 'surname', 'email');
            },
            'refererIt' => function ($query) {
                $query->select('id', 'name', 'surname', 'email');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'surname', 'email', 'is_admin');
            },
            'stage' => function ($query) {
                $query->select('id', 'name');
            },
        ]);

        // Generazione chiave cache in base all'utente e ai parametri della richiesta.
        if ($withClosed) {
            $cacheKey = 'user_'.$user->id.'_'.$selectedCompanyId.'_tickets_with_closed';
        } else {
            $cacheKey = 'user_'.$user->id.'_'.$selectedCompanyId.'_tickets';
        }

        // Recupero dati e salvataggio in cache per 5 minuti
        $tickets = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query) {

            $ticketsTemp = $query->get();

            foreach ($ticketsTemp as $ticket) {
                if ($ticket->user && $ticket->user->is_admin) {
                    $ticket->user->id = 1;
                    $ticket->user->name = 'Supporto';
                    $ticket->user->surname = '';
                    $ticket->user->email = 'Supporto';
                }
            }

            return $ticketsTemp;
        });

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        return response([
            'message' => 'Please use /api/store to create a new ticket',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $user = $request->user();
        $newTicketStageId = TicketStage::where('system_key', 'new')->value('id');
        $closedTicketStageId = TicketStage::where('system_key', 'closed')->value('id');

        $fields = $request->validate([
            'description' => 'required|string',
            'type_id' => 'required|int',
            'referer_it' => 'required|int',
        ]);

        DB::beginTransaction();

        try {
            $ticketType = TicketType::find($fields['type_id']);
            $group = $ticketType->groups->first();
            $groupId = $group ? $group->id : null;

            if (! $ticketType) {
                return response([
                    'message' => 'Ticket type not found',
                ], 404);
            }

            if($ticketType->is_scheduling == 1 && !$request->scheduledDuration){
                return response([
                    'message' => 'La durata pianificata è obbligatoria per questo tipo di ticket',
                ], 400);
            }

            $slaveTicketsRequest = collect();
            if ($ticketType->is_master == 1) {

                $slaveTypes = $ticketType->slaveTypes();
                
                $decodedSlaveTickets = $request->slaveTickets ? json_decode($request->slaveTickets, true) : [];
                $slaveTicketsRequest = collect($decodedSlaveTickets);

                if (
                    !isset($request->slaveTickets) ||
                    ($slaveTypes->wherePivot('is_required', 1)->pluck('ticket_types.id')->diff($slaveTicketsRequest->pluck('type_id'))->count() > 0)
                ) {
                    return response([
                        'message' => 'Dati mancanti o incompleti per i ticket collegati. ' 
                        . $slaveTypes->wherePivot('is_required', 1)->pluck('id')->diff($slaveTicketsRequest->pluck('type_id'))->count()
                        . ' di '. $slaveTypes->wherePivot('is_required', 1)->count(),
                    ], 400);
                }
            }

            // Admin o con ticketType.company_id tra le sue aziende
            $selectedCompanyId = $this->getSelectedCompanyId($user);
            if (! $user->is_admin && (! $selectedCompanyId || $selectedCompanyId != $ticketType->company_id)) {
                return response([
                    'message' => 'Unauthorized',
                ], 401);
            }

            $ticket = Ticket::create([
                'description' => $fields['description'],
                'type_id' => $fields['type_id'],
                'group_id' => $groupId,
                'user_id' => $user->id,
                'status' => '0', // per il momento lo lasciamo perchè non ha un valore di default. Poi questo campo verrà eliminato.
                'stage_id' => $newTicketStageId,
                'company_id' => isset($request['company']) && $user['is_admin'] == 1 ? $request['company'] : $ticketType->company_id,
                'file' => null,
                'duration' => 0,
                'sla_take' => $ticketType['default_sla_take'],
                'sla_solve' => $ticketType['default_sla_solve'],
                'priority' => $ticketType['default_priority'],
                'unread_mess_for_adm' => $user['is_admin'] == 1 ? 0 : 1,
                'unread_mess_for_usr' => $user['is_admin'] == 1 ? 1 : 0,
                'source' => $user['is_admin'] == 1 ? ($request->source ?? null) : 'platform',
                'is_user_error' => 1, // is_user_error viene usato per la responsabilità del dato e di default è assegnata al cliente.
                'is_billable' => $ticketType['expected_is_billable'],
                'referer_it_id' => $request->referer_it ?? null,
                'referer_id' => $request->referer ?? null,
                'scheduled_duration' => $request->scheduledDuration ?? null,
            ]);

            if (Feature::for(config('app.tenant'))->active('ticket.show_visibility_fields')) {
                $domustartTicket = DomustartTicket::firstOrNew(['id' => $ticket->id]);
                $domustartTicket->is_visible_all_users = $request->is_visible_all_users ? 1 : 0;
                $domustartTicket->is_visible_admin = $request->is_visible_admin ? 1 : 0;
                $domustartTicket->save();
            }

            if ($request->parent_ticket_id) {
                $parentTicket = Ticket::find($request->parent_ticket_id);
                if ($parentTicket) {
                    // Se il padre ha già un figlio non può averne un altro.
                    if (Ticket::where('parent_ticket_id', $parentTicket->id)->exists()) {
                        return response([
                            'message' => 'Il ticket padre ha già un figlio. Impossibile associarne altri.',
                        ], 400);
                    }

                    $ticket->parent_ticket_id = $parentTicket->id;
                    $ticket->save();

                    // Salva lo stato del ticket prima di modificarlo
                    $parentOldStageId = $parentTicket->stage_id;

                    // Chiude il ticket padre, segnala che il ticket procede in quello nuovo
                    $parentTicket->stage_id = $closedTicketStageId;
                    $parentTicket->is_rejected = 1;
                    $parentTicket->is_form_correct = 0;

                    $parentTicket->save();

                    TicketStatusUpdate::create([
                        'ticket_id' => $parentTicket->id,
                        'user_id' => $user->id,
                        'old_stage_id' => $parentOldStageId,
                        'new_stage_id' => $closedTicketStageId,
                        'content' => 'Ticket chiuso automaticamente in quanto è stato aperto un nuovo ticket collegato: '.$ticket->id,
                        'type' => 'closing',
                    ]);

                    TicketStatusUpdate::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'content' => 'Questo ticket è stato aperto come continuazione del ticket: '.$parentTicket->id,
                        'type' => 'note',
                    ]);

                    // Invalida la cache per chi ha creato il ticket e per i referenti.

                    $parentTicket->invalidateCache();
                }
            }

            // Richiesta di riapertura ticket. Tutti possono riaprire un ticket, entro 7 giorni dalla chiusura.
            if ($request->reopen_parent_ticket_id) {
                $reopenedTicket = Ticket::find($request->reopen_parent_ticket_id);
                if ($reopenedTicket) {
                    if ($reopenedTicket->stage_id != $closedTicketStageId) {
                        return response([
                            'message' => 'Il ticket non è chiuso. Impossibile riaprirlo.',
                        ], 400);
                    }
                    // Se il ticket con l'id da inserire in reopen_parent_id è già stato riaperto, non può essere riaperto di nuovo (si dovrebbe riaprire quello successivo).
                    $existingChildTicket = Ticket::where('reopen_parent_id', $reopenedTicket->id)->first();
                    if ($existingChildTicket) {
                        return response([
                            'message' => 'Il ticket è già stato riaperto. Impossibile riaprirlo nuovamente. Provare col ticket '.$existingChildTicket->id,
                        ], 400);
                    }

                    // Se il ticket è stato chiuso e sono passati più di 7 giorni dalla chiusura, non può essere riaperto.
                    $can_reopen = false;
                    $closingUpdate = $reopenedTicket->statusUpdates()
                        ->where('type', 'closing')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    if ($closingUpdate) {
                        $can_reopen = (time() - strtotime($closingUpdate->created_at)) < (7 * 24 * 60 * 60);
                    }
                    if (! $can_reopen) {
                        return response([
                            'message' => 'Il ticket è stato chiuso da più di 7 giorni. Impossibile riaprirlo.',
                        ], 400);
                    }

                    $ticket->reopen_parent_id = $reopenedTicket->id;
                    $ticket->save();

                    TicketStatusUpdate::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'content' => 'Questo ticket è stato aperto come riapertura del ticket: '.$reopenedTicket->id,
                        'type' => 'note',
                    ]);

                    // Invalida la cache per chi ha creato il ticket e per i referenti.
                    // Anche se non ci sono modifiche al modello la invalidiamo perchè nella risposta potremmo inserire dati sulla riapertura.
                    $reopenedTicket->invalidateCache();
                }
            }

            if ($request->file('file') != null) {
                $file = $request->file('file');
                $file_name = time().'_'.$file->getClientOriginalName();
                $storeFile = FileUploadController::storeFile($file, 'tickets/'.$ticket->id.'/', $file_name);
                $ticket->update([
                    'file' => $file_name,
                ]);
            }

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => json_encode($request['messageData']),
                // 'is_read' => 1
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $fields['description'],
                // 'is_read' => 0
            ]);

            // Associazioni ticket-hardware
            $hardwareFields = $ticketType->typeHardwareFormField();
            $addedHardware = [];
            foreach ($hardwareFields as $field) {
                if (isset($request['messageData'][$field->field_label])) {
                    $hardwareIds = $request['messageData'][$field->field_label];
                    foreach ($hardwareIds as $id) {
                        $hardware = Hardware::find($id);
                        if (!$hardware) {
                            throw new \Exception('Hardware con id '.$id.' non trovato.');
                        }
                        $ticket->hardware()->syncWithoutDetaching($id);
                        if (! in_array($id, $addedHardware)) {
                            $addedHardware[] = $id;
                        }
                    }
                }
            }
            HardwareAuditLog::create([
                'modified_by' => $user->id,
                'log_subject' => 'hardware_ticket',
                'log_type' => 'create',
                'new_data' => json_encode([
                    'ticket_id' => $ticket->id,
                    'hardware_ids' => $addedHardware,
                ]),
            ]);

            $additionalInformationsForSlaveTickets = array_filter([
                'office' => $request['messageData']['office'] ?? null,
                'referer' => $request['referer'] ?? null,
                'referer_it' => $request['referer_it'] ?? null,
            ], function ($value) {
                return $value !== null;
            });

            foreach ($slaveTicketsRequest as $slaveTicketToStore) {
                $this->storeSlaveTickets($slaveTicketToStore, $ticket, $additionalInformationsForSlaveTickets);
            }

            DB::commit();

            cache()->forget('user_'.$user->id.'_tickets');
            cache()->forget('user_'.$user->id.'_tickets_with_closed');

            $brand_url = $ticket->brandUrl();

            dispatch(new SendOpenTicketEmail($ticket, $brand_url));

            return response([
                'ticket' => $ticket,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Errore durante la creazione del ticket. Request: '.json_encode($request).' - Errore: '.$e->getMessage());

            return response([
                'message' => 'Errore durante la creazione del ticket: '.$e->getMessage(),
            ], 500);
        }
    }

    private function storeSlaveTickets($slaveTicketToStore, $masterTicket, $additionalInformationsForSlaveTickets)
    {
        try {
            $user = $masterTicket->user;
            $newTicketStageId = TicketStage::where('system_key', 'new')->value('id');

            $ticketType = TicketType::find($slaveTicketToStore['type_id']);
            $group = $ticketType->groups->first();
            $groupId = $group ? $group->id : null;

            if (!$ticketType) {
                throw new \Exception('Tipo di ticket non trovato.' . $slaveTicketToStore['type_id']);
            }
            if ($masterTicket->company_id != $ticketType->company_id) {
                throw new \Exception('Il tipo di ticket non appartiene alla stessa compagnia del ticket master. Master: ' . $masterTicket->company_id . ' - Collegato: ' . $ticketType->company_id);
            }
            if ($ticketType->is_master == 1) {
                throw new \Exception('Non si può collegare un\'operazione strutturata a un\'altra. Tipo: ' . $ticketType->name);
            }
            if ($ticketType->is_scheduling == 1) {
                throw new \Exception('Non si può collegare un\'attività pianificata a un\'operazione strutturata. Tipo: ' . $ticketType->name);
            }
            if ($ticketType->is_grouping == 1) {
                throw new \Exception('Non si può collegare un\'ticket di raggruppamento a un\'operazione strutturata. Tipo: ' . $ticketType->name);
            }

            $newSlaveTicket = Ticket::create([
                'description' => $slaveTicketToStore['description'],
                'type_id' => $slaveTicketToStore['type_id'],
                'group_id' => $groupId,
                'user_id' => $masterTicket->user_id,
                'status' => '0', // per il momento lo lasciamo perchè non ha un valore di default. Poi questo campo verrà eliminato.
                'stage_id' => $newTicketStageId,
                'company_id' => $masterTicket->company_id ?? $ticketType->company_id,
                'file' => null,
                'duration' => 0,
                'sla_take' => $ticketType['default_sla_take'],
                'sla_solve' => $ticketType['default_sla_solve'],
                'priority' => $ticketType['default_priority'],
                'unread_mess_for_adm' => $user['is_admin'] == 1 ? 0 : 1,
                'unread_mess_for_usr' => $user['is_admin'] == 1 ? 1 : 0,
                'source' => $user['is_admin'] == 1 ? ($masterTicket->source ?? null) : 'platform',
                'is_user_error' => 1, // is_user_error viene usato per la responsabilità del dato e di default è assegnata al cliente.
                'is_billable' => $ticketType['expected_is_billable'],
                'referer_it_id' => $masterTicket->referer_it_id ?? null,
                'referer_id' => $masterTicket->referer_id ?? null,
                'master_id' => $masterTicket->id,
            ]);

            if (Feature::for(config('app.tenant'))->active('ticket.show_visibility_fields')) {
                $domustartTicket = DomustartTicket::firstOrNew(['id' => $newSlaveTicket->id]);
                $domustartTicket->is_visible_all_users = $masterTicket->is_visible_all_users ? 1 : 0;
                $domustartTicket->is_visible_admin = $masterTicket->is_visible_admin ? 1 : 0;
                $domustartTicket->save();
            }

            // Al momento il parent ticket si prende dal master ticket se serve. (non si riporta nello slave)
            // if ($request->parent_ticket_id) {
            //     $parentTicket = Ticket::find($request->parent_ticket_id);
            //     if ($parentTicket) {
            //         // Se il padre ha già un figlio non può averne un altro.
            //         if (Ticket::where('parent_ticket_id', $parentTicket->id)->exists()) {
            //             return response([
            //                 'message' => 'Il ticket padre ha già un figlio. Impossibile associarne altri.',
            //             ], 400);
            //         }

            //         $ticket->parent_ticket_id = $parentTicket->id;
            //         $ticket->save();

            //         // Salva lo stato del ticket prima di modificarlo
            //         $parentOldStageId = $parentTicket->stage_id;

            //         // Chiude il ticket padre, segnala che il ticket procede in quello nuovo
            //         $parentTicket->stage_id = $closedTicketStageId;
            //         $parentTicket->is_rejected = 1;
            //         $parentTicket->is_form_correct = 0;

            //         $parentTicket->save();

            //         TicketStatusUpdate::create([
            //             'ticket_id' => $parentTicket->id,
            //             'user_id' => $user->id,
            //             'old_stage_id' => $parentOldStageId,
            //             'new_stage_id' => $closedTicketStageId,
            //             'content' => 'Ticket chiuso automaticamente in quanto è stato aperto un nuovo ticket collegato: '.$ticket->id,
            //             'type' => 'closing',
            //         ]);

            //         TicketStatusUpdate::create([
            //             'ticket_id' => $ticket->id,
            //             'user_id' => $user->id,
            //             'content' => 'Questo ticket è stato aperto come continuazione del ticket: '.$parentTicket->id,
            //             'type' => 'note',
            //         ]);

            //         // Invalida la cache per chi ha creato il ticket e per i referenti.

            //         $parentTicket->invalidateCache();
            //     }
            // }

            // Se è una riapertura si vede dal master ticket. non si riporta nello slave.
            // Richiesta di riapertura ticket. Tutti possono riaprire un ticket, entro 7 giorni dalla chiusura.
            // if ($request->reopen_parent_ticket_id) {
            //     $reopenedTicket = Ticket::find($request->reopen_parent_ticket_id);
            //     if ($reopenedTicket) {
            //         if ($reopenedTicket->stage_id != $closedTicketStageId) {
            //             return response([
            //                 'message' => 'Il ticket non è chiuso. Impossibile riaprirlo.',
            //             ], 400);
            //         }
            //         // Se il ticket con l'id da inserire in reopen_parent_id è già stato riaperto, non può essere riaperto di nuovo (si dovrebbe riaprire quello successivo).
            //         $existingChildTicket = Ticket::where('reopen_parent_id', $reopenedTicket->id)->first();
            //         if ($existingChildTicket) {
            //             return response([
            //                 'message' => 'Il ticket è già stato riaperto. Impossibile riaprirlo nuovamente. Provare col ticket '.$existingChildTicket->id,
            //             ], 400);
            //         }

            //         // Se il ticket è stato chiuso e sono passati più di 7 giorni dalla chiusura, non può essere riaperto.
            //         $can_reopen = false;
            //         $closingUpdate = $reopenedTicket->statusUpdates()
            //             ->where('type', 'closing')
            //             ->orderBy('created_at', 'desc')
            //             ->first();
            //         if ($closingUpdate) {
            //             $can_reopen = (time() - strtotime($closingUpdate->created_at)) < (7 * 24 * 60 * 60);
            //         }
            //         if (! $can_reopen) {
            //             return response([
            //                 'message' => 'Il ticket è stato chiuso da più di 7 giorni. Impossibile riaprirlo.',
            //             ], 400);
            //         }

            //         $ticket->reopen_parent_id = $reopenedTicket->id;
            //         $ticket->save();

            //         TicketStatusUpdate::create([
            //             'ticket_id' => $ticket->id,
            //             'user_id' => $user->id,
            //             'content' => 'Questo ticket è stato aperto come riapertura del ticket: '.$reopenedTicket->id,
            //             'type' => 'note',
            //         ]);

            //         // Invalida la cache per chi ha creato il ticket e per i referenti.
            //         // Anche se non ci sono modifiche al modello la invalidiamo perchè nella risposta potremmo inserire dati sulla riapertura.
            //         $reopenedTicket->invalidateCache();
            //     }
            // }

            // if ($request->file('file') != null) {
            //     $file = $request->file('file');
            //     $file_name = time().'_'.$file->getClientOriginalName();
            //     $storeFile = FileUploadController::storeFile($file, 'tickets/'.$ticket->id.'/', $file_name);
            //     $ticket->update([
            //         'file' => $file_name,
            //     ]);
            // }

            $slaveTicketToStore['messageData'] = array_merge($slaveTicketToStore['messageData'], $additionalInformationsForSlaveTickets);
            TicketMessage::create([
                'ticket_id' => $newSlaveTicket->id,
                'user_id' => $user->id,
                'message' => json_encode($slaveTicketToStore['messageData']),
                // 'is_read' => 1
            ]);

            TicketMessage::create([
                'ticket_id' => $newSlaveTicket->id,
                'user_id' => $user->id,
                'message' => $slaveTicketToStore['description'],
                // 'is_read' => 0
            ]);

            // Associazioni ticket-hardware
            $hardwareFields = $ticketType->typeHardwareFormField();
            $addedHardware = [];
            foreach ($hardwareFields as $field) {
                if (isset($slaveTicketToStore['messageData'][$field->field_label])) {
                    $hardwareIds = $slaveTicketToStore['messageData'][$field->field_label];
                    foreach ($hardwareIds as $id) {
                        $hardware = Hardware::find($id);
                        if (!$hardware) {
                            throw new \Exception('Hardware con id '.$id.' non trovato.');
                        }
                        $newSlaveTicket->hardware()->syncWithoutDetaching($id);
                        if (! in_array($id, $addedHardware)) {
                            $addedHardware[] = $id;
                        }
                    }
                }
            }
            HardwareAuditLog::create([
                'modified_by' => $user->id,
                'log_subject' => 'hardware_ticket',
                'log_type' => 'create',
                'new_data' => json_encode([
                    'ticket_id' => $newSlaveTicket->id,
                    'hardware_ids' => $addedHardware,
                ]),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new \Exception('Dati mancanti o non validi per i ticket collegati. ' . $e->getMessage());
        }
        
    }

    /**
     * Store newly created resources in storage, starting from a file.
     */
    public function storeMassive(Request $request)
    {
        $request->validate([
            'data' => 'required|string',
            'file' => 'required|file|mimes:xlsx,csv',
        ]);

        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $data = json_decode($request->data);

        $additionalData = []; // I tuoi dati aggiuntivi

        $additionalData['user'] = $user;
        $additionalData['formData'] = $data;

        try {
            Excel::import(new TicketsImport($additionalData), $request->file('file'));

            return response()->json(['success' => true, 'message' => 'Importazione completata con successo.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Errore durante l\'importazione.\\n\\n'.$e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        $ticket = Ticket::where('id', $id)->with([
            'ticketType' => function ($query) {
                $query->with('category');
            },
            'hardware' => function ($query) {
                $query->with('hardwareType');
            },
            'company' => function ($query) {
                $query->select('id', 'name', 'logo_url');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'surname', 'email', 'is_admin');
            },
            'stage' => function ($query) {
                $query->select('id', 'name', 'description', 'admin_color', 'user_color', 'is_sla_pause', 'system_key');
            },
            'files',
        ])->first();

        if ($ticket == null) {
            return response([
                'message' => 'Ticket not found',
            ], 404);
        }

        // 2. Controlla autorizzazione
        if (! $this->canViewTicket($user, $ticket)) {
            return response(['message' => 'Unauthorized'], 401);
        }

        // 3. Applica trasformazioni per il frontend
        $this->maskSupportUserIfNeeded($user, $ticket);
        $this->markMessagesAsRead($user, $ticket);
        $this->addVirtualFields($ticket);

        // Aggiungere alla fine i dati che servono solo nella risposta e non vanno salvati nel DB
        if($ticket->ticketType->is_master == 1 && $user->is_master == 1){
            // Usa setAttribute invece di assegnazione diretta per evitare che venga considerato "dirty"
            $ticket->setAttribute('slavesActualProcessingTimesSum', $ticket->slaves()->sum('actual_processing_time') ?? 0);
        }

        return response([
            'ticket' => $ticket,
            'from' => time(),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ticket $ticket)
    {

        return response([
            'message' => 'Please use /api/update to update an existing ticket',
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ticket $ticket)
    {
        //

        $user = $request->user();
        // Ricrea la stringa della cacheKey per invalidarla, visto che c'è stata una modifica.
        $cacheKey = 'user_'.$user->id.'_tickets_show:'.$ticket->id;

        $fields = $request->validate([
            'duration' => 'required|string',
            'due_date' => 'required|date',
        ]);

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', $user->id)->first();

        $ticket->update([
            'duration' => $fields['duration'],
            'due_date' => $fields['due_date'],
        ]);

        cache()->forget($cacheKey);

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ticket $ticket, Request $request)
    {
        //
        $user = $request->user();

        $closedTicketStageId = TicketStage::where('system_key', 'closed')->value('id');

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', $user->id)->first();
        cache()->forget('user_'.$user->id.'_tickets');
        cache()->forget('user_'.$user->id.'_tickets_with_closed');

        $ticket->update([
            'stage_id' => $closedTicketStageId,
        ]);
    }

    public function updateStatus(Ticket $ticket, Request $request)
    {
        $closedTicketStageId = TicketStage::where('system_key', 'closed')->value('id');

        // Controlla se lo status è presente nella richiesta e se è tra quelli validi.
        $request->validate([
            'stage_id' => 'required|int|exists:ticket_stages,id,deleted_at,NULL',
        ]);
        $isAdminRequest = $request->user()['is_admin'] == 1;

        if (! $isAdminRequest) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // Se lo status della richiesta è uguale a quello attuale, non fa nulla.
        if ($ticket->stage_id == $request->stage_id) {
            return response([
                'message' => 'The ticket is already in this status.',
            ], 200);
        }

        // Se lo status corrisponde a "Chiuso", non permette la modifica.
        // si può chiudere solo usando l'apposita funzione di chiusura, che fa i controlli e richiede le informazioni necessarie.
        // $index_status_chiuso = array_search('Chiuso', $ticketStages); // dovrebbe essere 5
        // if ($request->status == $index_status_chiuso) {
        if ($request->stage_id == $closedTicketStageId) {
            return response([
                'message' => 'It\'s not possible to close the ticket from here',
            ], 400);
        }

        // Se il ticket è chiuso, lo stato non può essere modificato.
        // if ($ticket->status == $index_status_chiuso) {
        if ($ticket->stage_id == $closedTicketStageId) {
            return response([
                'message' => 'The ticket is already closed. It cannot be modified.',
            ], 400);
        }

        $oldStageId = $ticket->stage_id;

        $ticket->fill([
            'stage_id' => $request->stage_id,
            'wait_end' => $request['wait_end'],
        ])->save();

        $newTicketStageText = TicketStage::find($request->stage_id)->name;

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'old_stage_id' => $oldStageId,
            'new_stage_id' => $request->stage_id,
            // 'content' => 'Stato del ticket modificato in "'.$ticketStages[$request->status].'"',
            'content' => 'Stato del ticket modificato in "'.$newTicketStageText.'"',
            'type' => 'status',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Invalida la cache per chi ha creato il ticket e per i referenti.
        $ticket->invalidateCache();

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function addNote(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'message' => 'required|string',
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $request->message,
            'type' => 'note',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'new-note' => $request->message,
        ], 200);
    }

    public function updateTicketPriority(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'priority' => 'required|string',
        ]);

        if ($request->user()['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $priorities = ['low', 'medium', 'high', 'critical']; // Define the priorities array

        if (! in_array($fields['priority'], $priorities)) {
            return response([
                'message' => 'Invalid priority value.',
            ], 400);
        }

        $company = $ticket->company;
        $sla_take_key = 'sla_take_'.$fields['priority'];
        $sla_solve_key = 'sla_solve_'.$fields['priority'];
        $new_sla_take = $company[$sla_take_key];
        $new_sla_solve = $company[$sla_solve_key];

        if ($new_sla_take == null || $new_sla_solve == null) {
            return response([
                'message' => 'Company sla for '.$fields['priority'].' priority must be set.',
            ], 400);
        }

        $old_priority = (isset($ticket['priority']) && strlen($ticket['priority']) > 0) ? $ticket['priority'] : 'not set';

        $ticket->update([
            'priority' => $fields['priority'],
            'sla_take' => $new_sla_take,
            'sla_solve' => $new_sla_solve,
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => 'Priorità del ticket modificata da '.$old_priority.' a '.$fields['priority'].'. SLA aggiornata di conseguenza.',
            'type' => 'sla',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicketIsBillable(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'is_billable' => 'required|boolean',
        ]);

        if ($request->user()['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // Verifica se il campo 'is_billable' è stato modificato
        $ticket->is_billable = $fields['is_billable'];
        $isValueChanged = $ticket->isDirty('is_billable');

        if (! $isValueChanged) {
            return response([
                'message' => 'Nessuna modifica apportata alla fatturabilità del ticket.',
            ], 400);
        }

        if ($ticket->is_billing_validated == 1) {
            // Se la fatturabilità è già stata validata, non può essere modificata.
            return response([
                'message' => 'La fatturabilità del ticket è già stata validata. Non può essere modificata.',
            ], 400);
        }

        $ticket->save();

        // Refresh del ticket per assicurarsi che i cast siano applicati
        $ticket->refresh();

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => 'Ticket impostato come: '.($fields['is_billable'] ? 'Fatturabile' : 'Non fatturabile'),
            'type' => 'billing',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicketIsBilled(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'is_billed' => 'required|boolean',
        ]);

        if ($request->user()['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // Verifica se il campo 'is_billed' è stato modificato
        $ticket->is_billed = $fields['is_billed'];
        $isValueChanged = $ticket->isDirty('is_billed');

        if ($isValueChanged) {
            $ticket->save();

            // Refresh del ticket per assicurarsi che i cast siano applicati
            $ticket->refresh();

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => 'Ticket impostato come: '.($fields['is_billed'] ? 'Fatturato' : 'Non fatturato'),
                'type' => 'billing',
            ]);

            // dispatch(new SendUpdateEmail($update));
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicketBillDetails(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'bill_identification' => 'nullable|string|max:255',
            'bill_date' => 'nullable|date',
        ]);

        if ($request->user()['is_admin'] != 1 || ($request->user()['is_superadmin'] != 1)) {
            return response([
                'message' => 'The user must be superadmin.',
            ], 401);
        }

        // Verifica se almeno uno dei campi è stato modificato
        $ticket->bill_identification = $fields['bill_identification'];
        $ticket->bill_date = $fields['bill_date'];

        $isValueChanged = $ticket->isDirty('bill_identification') || $ticket->isDirty('bill_date');

        if ($isValueChanged) {
            $ticket->save();

            // Refresh del ticket per assicurarsi che i cast siano applicati
            $ticket->refresh();

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => 'Dettagli fatturazione aggiornati: '.
                    'ID Fattura: '.($fields['bill_identification'] ?? 'N/A').', '.
                    'Data: '.($fields['bill_date'] ? date('d/m/Y', strtotime($fields['bill_date'])) : 'N/A'),
                'type' => 'billing',
            ]);

            dispatch(new SendUpdateEmail($update));
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicketBillingInfo(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'is_billable' => 'nullable|boolean',
            'is_billed' => 'nullable|boolean',
            'bill_identification' => 'nullable|string|max:255',
            'bill_date' => 'nullable|date',
            'is_billing_validated' => 'sometimes|boolean',
        ]);

        if ($request->user()['is_admin'] != 1 || ($request->user()['is_superadmin'] != 1)) {
            return response([
                'message' => 'The user must be superadmin.',
            ], 401);
        }

        // Validazione custom: is_billing_validated può essere impostato solo se is_billable è stato impostato (true o false, ma non null)
        if (isset($fields['is_billing_validated'])) {
            // Determina il valore finale di is_billable considerando che null nella richiesta significa "mantieni il valore corrente"
            $finalIsBillable = (isset($fields['is_billable']) && ($fields['is_billable'] !== null))
                ? $fields['is_billable']
                : $ticket->is_billable;

            if ($fields['is_billing_validated'] == 1 && ($finalIsBillable === null)) {
                return response([
                    'message' => 'Non è possibile validare la fatturazione se la fatturabilità del ticket non è ancora stata definita.',
                    'error' => 'is_billing_validated can only be set to true when is_billable has been explicitly set (true or false)',
                ], 422);
            }
        }

        $changes = [];
        $originalValues = [];

        // Prepara i campi da aggiornare e traccia le modifiche
        foreach ($fields as $field => $value) {
            if ($value !== null && $value !== $ticket->$field) {
                $originalValues[$field] = $ticket->$field;
                $ticket->$field = $value;
                $changes[$field] = $value;
            }
        }

        // Se non ci sono modifiche, restituisce il ticket senza salvare
        if (empty($changes)) {
            return response([
                'ticket' => $ticket,
                'message' => 'Nessuna modifica rilevata.',
            ], 200);
        }

        $ticket->save();

        // Refresh del ticket per assicurarsi che i cast siano applicati
        $ticket->refresh();

        // Genera il messaggio di log dettagliato
        $logMessages = [];

        if (isset($changes['is_billable'])) {
            $logMessages[] = 'Fatturabilità: '.($changes['is_billable'] ? 'Fatturabile' : 'Non fatturabile');
        }

        if (isset($changes['is_billed'])) {
            $logMessages[] = 'Fatturazione: '.($changes['is_billed'] ? 'Fatturato' : 'Non fatturato');
        }

        if (isset($changes['bill_identification'])) {
            $logMessages[] = 'ID Fattura: '.($changes['bill_identification'] ?? 'Rimosso');
        }

        if (isset($changes['bill_date'])) {
            $logMessages[] = 'Data Fatturazione: '.($changes['bill_date'] ? date('d/m/Y', strtotime($changes['bill_date'])) : 'Rimossa');
        }

        if (isset($changes['is_billing_validated'])) {
            $logMessages[] = 'Validazione: '.($changes['is_billing_validated'] ? 'Validato' : 'Non validato');
        }

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => 'Informazioni fatturazione aggiornate: '.implode(', ', $logMessages),
            'type' => 'billing',
        ]);

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
            'changes' => $changes,
        ], 200);
    }

    public function getTicketBlame(Ticket $ticket)
    {
        return response([
            'is_user_error' => $ticket['is_user_error'],
            'is_form_correct' => $ticket['is_form_correct'],
            'was_user_self_sufficient' => $ticket['was_user_self_sufficient'],
            'is_user_error_problem' => $ticket['is_user_error_problem'],
        ], 200);
    }

    public function updateTicketBlame(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'is_user_error' => 'required|boolean',
            'was_user_self_sufficient' => 'required|boolean',
            'is_form_correct' => 'required|boolean',
            'is_user_error_problem' => 'boolean',
        ]);

        if ($request->user()['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // Ottieni i campi di $fields che hanno un valore diverso dallo stesso campo in $ticket
        $dirtyFields = array_filter($fields, function ($value, $key) use ($ticket) {
            return $value !== $ticket->$key;
        }, ARRAY_FILTER_USE_BOTH);

        $ticket->update($dirtyFields);

        foreach ($dirtyFields as $key => $value) {
            $propertyText = '';
            $newValue = '';
            switch ($key) {
                case 'is_user_error':
                    $propertyText = 'Responsabilità del dato assegnata a: ';
                    $newValue = $value ? 'Cliente' : 'Supporto';
                    break;
                case 'was_user_self_sufficient':
                    $propertyText = 'Cliente autonomo impostato su: ';
                    $newValue = $value ? 'Si' : 'No';
                    break;
                case 'is_form_correct':
                    $propertyText = 'Form corretto impostato su: ';
                    $newValue = $value ? 'Si' : 'No';
                    break;
                case 'is_user_error_problem':
                    $propertyText = 'Responsabilità del problema assegnata a: ';
                    $newValue = $value ? 'Cliente' : 'Supporto';
                    break;
                default:
                    'Errore';
                    break;
            }

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => $propertyText.$newValue,
                'type' => 'blame',
            ]);

            dispatch(new SendUpdateEmail($update));
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicketActualProcessingTime(Ticket $ticket, Request $request)
    {
        $fields = $request->validate([
            'actual_processing_time' => 'required|int',
        ]);

        if ($request->user()['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        if($ticket->ticketType->is_master == 1) {
            return response([
                'message' => 'Non è possibile modificare il tempo di lavorazione effettivo di un\'operazione strutturata. Va calcolato sommando i tempi dei ticket collegati.',
            ], 400);
        }

        // Se il valore è diverso da quello già esistente, lo aggiorna
        if ($ticket->actual_processing_time != $fields['actual_processing_time']) {
            // Controlli vari sul tempo e poi aggiornamento dati e registrazione modifica.

            // Il tempo deve essere maggiore o uguale a 0 e un multiplo di 10 minuti, anche per i ticket ancora aperti.
            if ($fields['actual_processing_time'] < 0) {
                return response([
                    'message' => 'Actual processing time must be greater than or equal to 0.',
                ], 400);
            }
            if ($fields['actual_processing_time'] % 10 != 0) {
                return response([
                    'message' => 'Actual processing time must be a multiple of 10 minutes.',
                ], 400);
            }

            // Se il ticket è chiuso, il tempo deve essere maggiore di 0 e almeno uguale al tempo atteso, se impostato nel tipo di ticket.
            if ($ticket->stage_id == TicketStage::where('system_key', 'closed')->value('id')) {
                if ($fields['actual_processing_time'] < 0) {
                    return response([
                        'message' => 'Actual processing time must be greater than 0 for closed tickets.',
                    ], 400);
                }

                $ticketType = $ticket->ticketType;
                if ($ticketType->expected_processing_time && ($fields['actual_processing_time'] < $ticketType->expected_processing_time)) {
                    return response([
                        'message' => 'Actual processing time for closed tickets must be greater than or equal to the expected processing time for this ticket type.',
                    ], 400);
                }                
            }

            $ticket->update([
                'actual_processing_time' => $fields['actual_processing_time'],
            ]);

            $editMessage = 'Tempo di lavorazione effettivo modificato a '.
                str_pad(intval($fields['actual_processing_time'] / 60), 2, '0', STR_PAD_LEFT).':'.str_pad($fields['actual_processing_time'] % 60, 2, '0', STR_PAD_LEFT);

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => $editMessage,
                'type' => 'time',
            ]);

            dispatch(new SendUpdateEmail($update));
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicketWorkMode(Ticket $ticket, Request $request)
    {
        $workModes = config('app.work_modes');

        $fields = $request->validate([
            'work_mode' => ['required', 'string', 'in:'.implode(',', array_keys($workModes))],
        ]);

        if ($request->user()['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // Se il valore è diverso da quello già esistente, lo aggiorna
        if ($ticket->work_mode != $fields['work_mode']) {
            // Controlli vari sul tempo e poi aggiornamento dati e registrazione modifica.
            $ticket->update([
                'work_mode' => $fields['work_mode'],
            ]);

            $editMessage = 'Modalità di lavoro modificata in "'.$workModes[$fields['work_mode']].'"';

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => $editMessage,
                'type' => 'work_mode',
            ]);

            dispatch(new SendUpdateEmail($update));
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function closeTicket(Ticket $ticket, Request $request)
    {
        // Non è prevista l'associazione del ticket a un'operazione strutturata, in nessun momento successivo all'apertura della stessa.
        $fields = $request->validate([
            'message' => 'required|string',
            'workMode' => 'required|string',
            'isRejected' => 'required|boolean',
            'no_user_response' => 'boolean',
        ]);

        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'Only admins can close tickets.',
            ], 401);
        }

        $closedTicketStageId = TicketStage::where('system_key', 'closed')->value('id');

        $ticketType = $ticket->ticketType;
        
        // Controlli diversi se il ticket è o meno un'operazione strutturata
        if($ticketType->is_master == 1) {
            // Controlla se ci sono ticket slave non ancora chiusi
            $hasOpenSlaveTickets = $ticket->slaves()->where('stage_id', '!=', $closedTicketStageId)->exists();
            if ($hasOpenSlaveTickets) {
                return response([
                    'message' => 'Non è possibile chiudere un\'operazione strutturata con ticket collegati ancora aperti.',
                ], 400);
            }
            $fields['actualProcessingTime'] = null;

        } else {
            $request->validate([
                'actualProcessingTime' => 'required|int',
            ]);
            $fields['actualProcessingTime'] = $request->actualProcessingTime;

            if ($fields['actualProcessingTime'] <= 0 || ($fields['actualProcessingTime'] < ($ticketType->expected_processing_time ?? 0))) {
                return response([
                    'message' => 'Actual processing time must be set and greater than or equal to the minimum processing time for this ticket type.',
                ], 400);
            }
    
            if ($fields['actualProcessingTime'] % 10 != 0) {
                return response([
                    'message' => 'Actual processing time must be a multiple of 10 minutes.',
                ], 400);
            }
        }

        DB::beginTransaction();

        try {
            if (! $ticket->handler) {
                $ticketGroup = $ticket->group;
                $handlerAdmin = $authUser;
                if ($ticketGroup && ! $ticketGroup->users()->where('user_id', $authUser->id)->first()) {
                    // Non capita mai, ma se l'utente non è nel gruppo, si prende il primo admin del gruppo.
                    $groupUser = $ticketGroup->users()->where('is_admin', 1)->first();
                    if ($groupUser) {
                        $handlerAdmin = $groupUser;
                    }
                }

                $ticket->update([
                    'admin_user_id' => $handlerAdmin->id,
                ]);

                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $authUser->id,
                    'content' => "Modifica automatica: Ticket assegnato all'utente ".$handlerAdmin->name.' '.($handlerAdmin->surname ?? ''),
                    'type' => 'assign',
                ]);
            }

            $oldStageId = $ticket->stage_id;

            $ticket->update([
                'stage_id' => $closedTicketStageId,
                'actual_processing_time' => $fields['actualProcessingTime'],
                'work_mode' => $request->workMode,
                'is_rejected' => $request->isRejected,
                'no_user_response' => $fields['no_user_response'] ?? false,
            ]);

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $authUser->id,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $closedTicketStageId,
                'content' => $fields['message'],
                'type' => 'closing',
                'show_to_user' => $request->sendMail,
            ]);

            // Invalida la cache per chi ha creato il ticket e per i referenti.
            $ticket->invalidateCache();

            DB::commit();

            dispatch(new SendUpdateEmail($update));

            // Controllare se si deve inviare la mail (l'invio al data_owner e al cliente sono separati per dare maggiore scelta all'admin)
            if ($request->sendMail == true) {
                // Invio mail al cliente
                // sendMail($dafeultMail, $fields['message']);
                $brand_url = $ticket->brandUrl();
                dispatch(new SendCloseTicketEmail($ticket, $fields['message'], $brand_url));
            }

            // Controllare se si deve inviare la mail al data_owner (l'invio al data_owner e al cliente sono separati per dare maggiore scelta all'admin)
            if ($request->sendToDataOwner == true && (isset($ticket->company->data_owner_email) && filter_var($ticket->company->data_owner_email, FILTER_VALIDATE_EMAIL))) {
                // Invio mail al data_owner del cliente
                // sendMail($dafeultMail, $fields['message']);
                $brand_url = $ticket->brandUrl();
                dispatch(new SendCloseTicketEmail($ticket, $fields['message'], $brand_url, true));
            }

            return response([
                'ticket' => $ticket,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante la chiusura del ticket. Request: '.json_encode($request->all()).' - Errore: '.$e->getMessage());

            return response([
                'message' => 'Errore durante la chiusura del ticket: '.$e->getMessage(),
            ], 500);
        }
    }

    public function assignToGroup(Ticket $ticket, Request $request)
    {

        $request->validate([
            'group_id' => 'required|int',
        ]);
        $user = $request->user();
        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $group = Group::where('id', $request->group_id)->first();

        if ($group == null) {
            return response([
                'message' => 'Group not found',
            ], 404);
        }

        $ticket->update([
            'group_id' => $request->group_id,
        ]);

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => 'Ticket assegnato al gruppo '.$group->name,
            'type' => 'group_assign',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Va rimosso l'utente assegnato al ticket se non fa parte del gruppo
        if ($ticket->admin_user_id && ! $group->users()->where('user_id', $ticket->admin_user_id)->first()) {
            $old_handler = User::find($ticket->admin_user_id);
            $ticket->update(['admin_user_id' => null]);

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => "Modifica automatica: Ticket rimosso dall'utente ".$old_handler->name.', perchè non è del gruppo '.$group->name,
                'type' => 'assign',
            ]);

            // Va modificato lo stato se viene rimosso l'utente assegnato al ticket. (solo se il ticket non è stato già chiuso)
            $newTicketStageId = TicketStage::where('system_key', 'new')->value('id');
            $closedTicketStageId = TicketStage::where('system_key', 'closed')->value('id');

            if ($ticket->stage_id != $newTicketStageId && $ticket->stage_id != $closedTicketStageId) {
                $oldStageId = $ticket->stage_id;
                $ticket->update(['stage_id' => $newTicketStageId]);
                $newStageText = TicketStage::find($newTicketStageId)->name;

                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'old_stage_id' => $oldStageId,
                    'new_stage_id' => $newTicketStageId,
                    'content' => 'Modifica automatica: Stato del ticket modificato in "'.$newStageText.'"',
                    'type' => 'status',
                ]);

                // Invalida la cache per chi ha creato il ticket e per i referenti.
                $ticket->invalidateCache();
            }
        }

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function assignToAdminUser(Ticket $ticket, Request $request)
    {

        $request->validate([
            'admin_user_id' => 'required|int',
        ]);
        $isAdminRequest = $request->user()['is_admin'] == 1;

        if (! $isAdminRequest) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $ticket->update([
            'admin_user_id' => $request->admin_user_id,
        ]);

        $adminUser = User::where('id', $request->admin_user_id)->first();

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => "Ticket assegnato all'utente ".$adminUser->name.' '.($adminUser->surname ?? ''),
            'type' => 'assign',
        ]);

        // Spostato dopo lo status update così la mail prende lo stato aggiornato
        // dispatch(new SendUpdateEmail($update));

        $newTicketStageId = TicketStage::where('system_key', 'new')->value('id');
        $assignedTicketStageId = TicketStage::where('system_key', 'assigned')->value('id');

        // Se lo stato è 'Nuovo' aggiornarlo in assegnato
        if ($ticket->stage_id == $newTicketStageId) {
            $oldStageId = $ticket->stage_id;
            $ticket->update(['stage_id' => $assignedTicketStageId]);
            $newStageText = TicketStage::find($assignedTicketStageId)->name;

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $assignedTicketStageId,
                'content' => 'Modifica automatica: Stato del ticket modificato in "'.$newStageText.'"',
                'type' => 'status',
            ]);

            // Invalida la cache per chi ha creato il ticket e per i referenti.
            $ticket->invalidateCache();
        }

        dispatch(new SendUpdateEmail($update));

        return response([
            'ticket' => $ticket,
        ], 200);
    }

    public function files(Ticket $ticket, Request $request)
    {
        $isAdminRequest = $request->user()['is_admin'] == 1;

        if ($isAdminRequest) {
            $files = TicketFile::where('ticket_id', $ticket->id)->get();
        } else {
            $files = TicketFile::where(['ticket_id' => $ticket->id, 'is_deleted' => false])->get();
        }

        return response([
            'files' => $files,
        ], 200);
    }

    public function storeFile($id, Request $request)
    {

        if ($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time().'_'.$file->getClientOriginalName();
            $path = 'tickets/'.$id.'/'.$file_name;
            $storeFile = FileUploadController::storeFile($file, 'tickets/'.$id.'/', $file_name);
            $ticketFile = TicketFile::create([
                'ticket_id' => $id,
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            return response([
                'ticketFile' => $ticketFile,
            ], 200);
        }
    }

    public function storeFiles($id, Request $request)
    {

        $authUser = $request->user();
        $ticket = Ticket::find($id);

        $selectedCompanyId = $this->getSelectedCompanyId($authUser);
        if (! $authUser->is_admin && (! $selectedCompanyId || $selectedCompanyId != $ticket->company_id)) {
            return response([
                'message' => 'Unauthorized.',
            ], 401);
        }

        if ($request->hasFile('files')) {
            $files = $request->file('files');
            $storedFiles = [];
            $count = 0;
            if (is_array($files)) {
                foreach ($files as $file) {
                    $file_name = time().'_'.$file->getClientOriginalName();
                    $path = 'tickets/'.$id.'/'.$file_name;
                    $storeFile = FileUploadController::storeFile($file, 'tickets/'.$id.'/', $file_name);
                    $ticketFile = TicketFile::create([
                        'ticket_id' => $id,
                        'filename' => $file->getClientOriginalName(),
                        'path' => $path,
                        'extension' => $file->getClientOriginalExtension(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);

                    $storedFiles[] = $ticketFile;
                    $count++;
                }
            } else {
                $file_name = time().'_'.$files->getClientOriginalName();
                $path = 'tickets/'.$id.'/'.$file_name;
                $storeFile = FileUploadController::storeFile($files, 'tickets/'.$id.'/', $file_name);
                $ticketFile = TicketFile::create([
                    'ticket_id' => $id,
                    'filename' => $files->getClientOriginalName(),
                    'path' => $path,
                    'extension' => $files->getClientOriginalExtension(),
                    'mime_type' => $files->getMimeType(),
                    'size' => $files->getSize(),
                ]);

                $storedFiles[] = $ticketFile;
                $count++;
            }

            return response([
                'ticketFiles' => $storedFiles,
                'filesCount' => $count,
            ], 200);
        }

        return response([
            'message' => 'No files uploaded.',
        ], 400);
    }

    public function generatedSignedUrlForFile($id)
    {
        $ticketFile = TicketFile::where('id', $id)->first();

        $url = FileUploadController::generateSignedUrlForFile($ticketFile->path);

        return response([
            'url' => $url,
        ], 200);
    }

    public function deleteFile($id, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Only admins can delete files.',
            ], 401);
        }

        $ticketFile = TicketFile::where('id', $id)->first();

        $success = $ticketFile->update([
            'is_deleted' => true,
        ]);

        if (! $success) {
            return response([
                'message' => 'Error deleting file.',
            ], 500);
        }

        return response([
            'message' => 'File deleted.',
        ], 200);
    }

    public function recoverFile($id, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Only admins can retcover files.',
            ], 401);
        }

        $ticketFile = TicketFile::where('id', $id)->first();

        $success = $ticketFile->update([
            'is_deleted' => false,
        ]);

        if (! $success) {
            return response([
                'message' => 'Error recovering file.',
            ], 500);
        }

        return response([
            'message' => 'File recovered.',
        ], 200);
    }

    /**
     * Show only the tickets belonging to the authenticated admin groups.
     */
    public function adminGroupsTickets(Request $request)
    {

        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $groups = $user->groups;

        $withClosed = $request->query('with-closed') == 'true' ? true : false;
        $closedStageId = TicketStage::where('system_key', 'closed')->value('id');

        if ($withClosed) {
            $tickets = Ticket::whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        } else {
            $tickets = Ticket::where('stage_id', '!=', $closedStageId)->whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show all tickets (if superadmin) or only the tickets belonging to the authenticated admin groups.
     */
    public function adminGroupsBillingTickets(Request $request)
    {
        // Se si vuole mostrare tutti i ticket a prescindere dal gruppo serve un superadmin. altrimenti si fanno vedere tutti e amen.
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be at least an admin.',
            ], 401);
        }

        $withClosed = $request->query('with-closed') == 'true' ? true : false;
        $withSet = $request->query('with-set') == 'true' ? true : false;
        $withValidated = $request->query('with-validated') == 'true' ? true : false;
        $companyId = $request->query('company') ? intval($request->query('company')) : null;

        if ($user['is_superadmin'] == 1) {
            $ticketsQuery = Ticket::with('stage');
        } else {
            $groups = $user->groups;
            $ticketsQuery = Ticket::with('stage')->whereIn('group_id', $groups->pluck('id'));
        }

        if (! $withSet) {
            $ticketsQuery->where('is_billable', null);
        }
        if (! $withClosed) {
            $closedStageId = TicketStage::where('system_key', 'closed')->value('id');
            $ticketsQuery->where('stage_id', '!=', $closedStageId);
        }
        if (! $withValidated) {
            $ticketsQuery->where('is_billing_validated', false);
        }
        if ($companyId) {
            $ticketsQuery->where('company_id', $companyId);
        }

        return response([
            'tickets' => $ticketsQuery->get(),
        ], 200);
    }

    /**
     * Show counters of all tickets (if superadmin) or only those belonging to the authenticated admin groups.
     */
    public function adminGroupsBillingCounters(Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be at least an admin.',
            ], 401);
        }

        $closedStageId = TicketStage::where('system_key', 'closed')->value('id');

        if ($user['is_superadmin'] == 1) {
            $counters = [
                'billable_missing' => 0,
                'billing_validation_missing' => 0,
                'billed_missing' => 0,
                'billed_bill_identification_missing' => 0,
                'billed_bill_date_missing' => 0,
                'open_billable_missing' => 0,
                'open_billing_validation_missing' => 0,
                'open_billed_missing' => 0,
                'open_billed_bill_identification_missing' => 0,
                'open_billed_bill_date_missing' => 0,
            ];
            // Singola query ottimizzata per tutti i contatori
            $counters = DB::selectOne('
                SELECT 
                    COUNT(CASE WHEN is_billable IS NULL THEN 1 END) as billable_missing,
                    COUNT(CASE WHEN is_billing_validated = 0 THEN 1 END) as billing_validation_missing,
                    COUNT(CASE WHEN is_billable = 1 AND is_billing_validated = 1 AND is_billed = 0 THEN 1 END) as billed_missing,
                    COUNT(CASE WHEN is_billed = 1 AND bill_identification IS NULL THEN 1 END) as billed_bill_identification_missing,
                    COUNT(CASE WHEN is_billed = 1 AND bill_date IS NULL THEN 1 END) as billed_bill_date_missing,
                    COUNT(CASE WHEN is_billable IS NULL AND stage_id != ? THEN 1 END) as open_billable_missing,
                    COUNT(CASE WHEN is_billing_validated = 0 AND stage_id != ? THEN 1 END) as open_billing_validation_missing,
                    COUNT(CASE WHEN is_billable = 1 AND is_billing_validated = 1 AND is_billed = 0 AND stage_id != ? THEN 1 END) as open_billed_missing,
                    COUNT(CASE WHEN is_billed = 1 AND bill_identification IS NULL AND stage_id != ? THEN 1 END) as open_billed_bill_identification_missing,
                    COUNT(CASE WHEN is_billed = 1 AND bill_date IS NULL AND stage_id != ? THEN 1 END) as open_billed_bill_date_missing
                FROM tickets
            ', [$closedStageId, $closedStageId, $closedStageId, $closedStageId, $closedStageId]);

            $counters = [
                'billable_missing' => $counters->billable_missing,
                'billing_validation_missing' => $counters->billing_validation_missing,
                'billed_missing' => $counters->billed_missing,
                'billed_bill_identification_missing' => $counters->billed_bill_identification_missing,
                'billed_bill_date_missing' => $counters->billed_bill_date_missing,
                'open_billable_missing' => $counters->open_billable_missing,
                'open_billing_validation_missing' => $counters->open_billing_validation_missing,
                'open_billed_missing' => $counters->open_billed_missing,
                'open_billed_bill_identification_missing' => $counters->open_billed_bill_identification_missing,
                'open_billed_bill_date_missing' => $counters->open_billed_bill_date_missing,
            ];
        } else {
            $counters = [
                'billable_to_set' => 0,
            ];
            $groups = $user->groups;
            $counters['billable_to_set'] += Ticket::whereIn('group_id', $groups->pluck('id'))
                ->where('is_billable', null)
                ->count();
        }

        return response([
            'counters' => $counters,
        ], 200);
    }

    /**
     * Show closing messages of the ticket
     */
    public function closingMessages(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        $selectedCompanyId = $this->getSelectedCompanyId($user);

        if ($user['is_admin'] != 1 && (! $selectedCompanyId || $selectedCompanyId != $ticket->company_id)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->get();

        return response([
            'closing_messages' => $closingUpdates,
        ], 200);
    }

    /**
     * Prende tutti i ticket associati a questa operazione strutturata.
     */
    public function getSlaveTickets(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        $selectedCompanyId = $this->getSelectedCompanyId($user);

        if ($user['is_admin'] != 1 &&
            ! ($user['is_company_admin'] == 1 && $selectedCompanyId && $ticket->company_id == $selectedCompanyId)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $slaveTickets = $ticket->slaves;

        foreach ($slaveTickets as $slaveTicket) {
            $slaveTicket->setVisible([
                'id',
                'company_id',
                'stage_id',
                'description',
                'group_id',
                'created_at',
                'type_id',
                'source',
                'parent_ticket_id',
                'master_id',
                'scheduling_id',
                'grouping_id',
            ]);
            $slaveTicket->user_full_name =
                $slaveTicket->user->is_admin == 1
                ? ('Supporto'.($user['is_admin'] != 1 ? ' - '.$slaveTicket->user->id : ''))
                : ($slaveTicket->user->surname
                    ? $slaveTicket->user->surname.' '.strtoupper(substr($slaveTicket->user->name, 0, 1)).'.'
                    : $slaveTicket->user->name
                );
            $slaveTicket->makeVisible(['user_full_name']);

            // Nome tipo ticket
            $ticketType = $slaveTicket->ticketType;
            $slaveTicket->ticket_type_name = $ticketType ? $ticketType->name : null;
            $slaveTicket->makeVisible(['ticket_type_name']);

            // Gestore (admin_user)
            $adminUser = $slaveTicket->adminUser;
            if ($adminUser) {
                $slaveTicket->admin_user_full_name = $adminUser->surname
                    ? $adminUser->surname.' '.strtoupper(substr($adminUser->name, 0, 1)).'.'
                    : $adminUser->name;
                $slaveTicket->makeVisible(['admin_user_full_name']);
            } else {
                $slaveTicket->admin_user_full_name = null;
                $slaveTicket->makeVisible(['admin_user_full_name']);
            }

            $referer = $slaveTicket->referer;
            if ($referer) {
                $slaveTicket->referer_full_name =
                    $referer->surname
                    ? $referer->surname.' '.strtoupper(substr($referer->name, 0, 1)).'.'
                    : $referer->name;
                $slaveTicket->makeVisible(['referer_full_name']);
            }
            $slaveTicket->ticket_type_name = $slaveTicket->ticketType ? $slaveTicket->ticketType->name : '';
            $slaveTicket->makeVisible(['ticket_type_name']);
        }

        return response([
            'slave_tickets' => $slaveTickets,
        ], 200);
    }

    public function getAvailableSchedulingTickets (Ticket $ticket, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($ticket->ticketType->is_scheduling == 1) {
            return response([
                'message' => 'This is a scheduling ticket. Scheduling tickets cannot be connected to other scheduling tickets.',
            ], 400);
        }

        $schedulingTickets = Ticket::whereHas('ticketType', function ($query) {
            $query->where('is_scheduling', true);
        })->where('company_id', $ticket->company_id)
        ->where('id', '!=', $ticket->id)
        // ->with([
        //     'referer' => function ($query) {
        //         $query->select('id', 'name', 'surname', 'email');
        //     },
        //     'refererIt' => function ($query) {
        //         $query->select('id', 'name', 'surname', 'email');
        //     },
        //     'user' => function ($query) {
        //         $query->select('id', 'name', 'surname', 'email', 'is_admin');
        //     },
        //     'stage' => function ($query) {
        //         $query->select('id', 'name');
        //     },
        //     'ticketType' => function ($query) {
        //         $query->select('id', 'name', 'is_scheduling');
        //     },
        // ])
        ->get();

        $schedulingTickets->each(function ($schedulingTicket) use ($user) {
            $schedulingTicket->user_full_name =
                $schedulingTicket->user->is_admin == 1
                ? ('Supporto'.($user['is_admin'] == 1 ? ' - '.$schedulingTicket->user->id : ''))
                : ($schedulingTicket->user->surname
                    ? $schedulingTicket->user->surname.' '.strtoupper(substr($schedulingTicket->user->name, 0, 1)).'.'
                    : $schedulingTicket->user->name
                );
            $schedulingTicket->makeVisible(['user_full_name']);

            // Nome tipo ticket
            $ticketType = $schedulingTicket->ticketType;
            $schedulingTicket->ticket_type_name = $ticketType ? $ticketType->name : null;
            $schedulingTicket->makeVisible(['ticket_type_name']);

            // Gestore (admin_user)
            $adminUser = $schedulingTicket->adminUser;
            if ($adminUser) {
                $schedulingTicket->admin_user_full_name = $adminUser->surname
                    ? $adminUser->surname.' '.strtoupper(substr($adminUser->name, 0, 1)).'.'
                    : $adminUser->name;
                $schedulingTicket->makeVisible(['admin_user_full_name']);
            } else {
                $schedulingTicket->admin_user_full_name = null;
                $schedulingTicket->makeVisible(['admin_user_full_name']);
            }

            $referer = $schedulingTicket->referer;
            if ($referer) {
                $schedulingTicket->referer_full_name =
                    $referer->surname
                    ? $referer->surname.' '.strtoupper(substr($referer->name, 0, 1)).'.'
                    : $referer->name;
                $schedulingTicket->makeVisible(['referer_full_name']);
            }
        });

        return response([
            'available_scheduling_tickets' => $schedulingTickets,
        ], 200);
    }

    public function connectToSchedulingTicket(Ticket $ticket, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($ticket->ticketType->is_scheduling == 1) {
            return response([
                'message' => 'Cannot connect a scheduling ticket to another scheduling ticket.',
            ], 400);
        }

        $fields = $request->validate([
            'scheduling_id' => 'required|int',
        ]);

        $schedulingTicket = Ticket::where('id', $fields['scheduling_id'])->whereHas('ticketType', function ($query) {
            $query->where('is_scheduling', true);
        })->first();

        if (! $schedulingTicket) {
            return response([
                'message' => 'Scheduling ticket not found.',
            ], 404);
        }

        if ($ticket->scheduling_id == $schedulingTicket->id) {
            return response([
                'message' => 'This ticket is already connected to the selected scheduling.',
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            $warning = "";

            // Assegna il ticket all'attività programmata
            $ticket->update(['scheduling_id' => $schedulingTicket->id]);

            // Se il ticket è un master, assegna anche tutti i suoi slave
            if ($ticket->ticketType->is_master == 1) {
                $slaveTickets = $ticket->slaves;
                $slaveCount = $slaveTickets->count();

                if ($slaveCount > 0) {
                    $slavesUpdated = false;
                    foreach ($slaveTickets as $slaveTicket) {
                        if($slaveTicket->scheduling_id != $schedulingTicket->id) {
                            $slaveTicket->update(['scheduling_id' => $schedulingTicket->id]);
                            $slavesUpdated = true;
                        }
                    }
                    if ($slavesUpdated) {
                        $warning .= "Attenzione: Questo è un tipo operazione strutturata. Sono stati collegati automaticamente anche i {$slaveCount} ticket associati. ";
                    }
                }
            }

            // Se il ticket ha un master, rimuovi l'associazione del master all'attività programmata precedente, perchè uno dei figli non è più associato a quella. Il master può essere associato solo se tutti i figli sono associati alla stessa.
            if ($ticket->master_id != null) {
                $masterTicket = Ticket::find($ticket->master_id);
                if($masterTicket) {
                    // Se il master è associato a un'attività programmata diversa da quella appena assegnata al figlio, rimuovi l'associazione del master.
                    if ($masterTicket->scheduling_id != null && $masterTicket->scheduling_id != $schedulingTicket->id) {
                        $masterTicket->update(['scheduling_id' => null]);
                        $warning .= "Attenzione: Questo ticket è associato ad un'operazione strutturata. L'operazione strutturata è stata rimossa dall'attività programmata a cui era associato in precedenza. ";
                    }
    
                    // Se tutti i figli del master sono ora associati alla stessa attività programmata, associa anche il master a quell'attività programmata.
                    $allSlaves = $masterTicket->slaves;
                    $allSlavesAssociatedToSameScheduling = true;
                    foreach ($allSlaves as $slave) {
                        if ($slave->scheduling_id != $schedulingTicket->id) {
                            $allSlavesAssociatedToSameScheduling = false;
                            break;
                        }
                    }

                    if ($allSlavesAssociatedToSameScheduling) {
                        $masterTicket->update(['scheduling_id' => $schedulingTicket->id]);
                        $warning .= "Attenzione: Questo ticket è associato ad un'operazione strutturata. Poiché tutti i ticket associati sono collegati alla stessa attività programmata, anche l'operazione strutturata è stata collegata automaticamente a tale attività programmata. ";
                    }
                }

            }

            DB::commit();

            return response([
                'ticket' => $ticket,
                'warning' => $warning ?? null,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response([
                'message' => 'Errore durante il collegamento all\'attività programmata: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function removeSchedulingConnection(Ticket $ticket, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($ticket->scheduling_id == null) {
            return response([
                'message' => 'This ticket is not connected to any scheduling.',
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            $warning = '';

            // Rimuovi l'associazione all'attività programmata
            $ticket->update(['scheduling_id' => null]);

            // Se il ticket ha un master, scollega il master dall'attività programmata.
            if ($ticket->master_id != null) {
                $masterTicket = Ticket::find($ticket->master_id);
                if ($masterTicket && $masterTicket->scheduling_id != null) {
                    $masterTicket->update(['scheduling_id' => null]);
                    $warning .= "Attenzione: Questo ticket è associato ad un'operazione strutturata. L'operazione strutturata è stata rimossa dall'attività programmata a cui era associata in precedenza.";
                }
            }

            // Se il ticket è un master non si deve fare altro, perchè gli slave possono essere associati singolarmente anche ad attività diverse.

            DB::commit();

            return response([
                'ticket' => $ticket,
                'warning' => $warning ?? null,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response([
                'message' => 'Errore durante la rimozione del collegamento all\'attività programmata: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getTicketsConnectedToScheduling(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        $selectedCompanyId = $this->getSelectedCompanyId($user);

        if ($user['is_admin'] != 1 &&
            ! ($user['is_company_admin'] == 1 && $selectedCompanyId && $ticket->company_id == $selectedCompanyId)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $connectedTickets = $ticket->schedulingSlaves;

        foreach ($connectedTickets as $connectedTicket) {
            $connectedTicket->setVisible([
                'id',
                'company_id',
                'stage_id',
                'description',
                'group_id',
                'created_at',
                'type_id',
                'source',
                'parent_ticket_id',
                'master_id',
                'scheduling_id',
                'grouping_id',
            ]);
            $connectedTicket->user_full_name =
                $connectedTicket->user->is_admin == 1
                ? ('Supporto'.($user['is_admin'] == 1 ? ' - '.$connectedTicket->user->id : ''))
                : ($connectedTicket->user->surname
                    ? $connectedTicket->user->surname.' '.strtoupper(substr($connectedTicket->user->name, 0, 1)).'.'
                    : $connectedTicket->user->name
                );
            $connectedTicket->makeVisible(['user_full_name']);

            // Nome tipo ticket
            $ticketType = $connectedTicket->ticketType;
            $connectedTicket->ticket_type_name = $ticketType ? $ticketType->name : null;
            $connectedTicket->makeVisible(['ticket_type_name']);

            // Gestore (admin_user)
            $adminUser = $connectedTicket->adminUser;
            if ($adminUser) {
                $connectedTicket->admin_user_full_name = $adminUser->surname
                    ? $adminUser->surname.' '.strtoupper(substr($adminUser->name, 0, 1)).'.'
                    : $adminUser->name;
                $connectedTicket->makeVisible(['admin_user_full_name']);
            } else {
                $connectedTicket->admin_user_full_name = null;
                $connectedTicket->makeVisible(['admin_user_full_name']);
            }

            $referer = $connectedTicket->referer;
            if ($referer) {
                $connectedTicket->referer_full_name =
                    $referer->surname
                    ? $referer->surname.' '.strtoupper(substr($referer->name, 0, 1)).'.'
                    : $referer->name;
                $connectedTicket->makeVisible(['referer_full_name']);
            }
            $connectedTicket->ticket_type_name = $connectedTicket->ticketType ? $connectedTicket->ticketType->name : '';
            $connectedTicket->makeVisible(['ticket_type_name']);
        }

        return response([
            'connected_tickets' => $connectedTickets,
        ], 200);
    }

    public function getSchedulingTicketRecapData (Ticket $ticket, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($ticket->ticketType->is_scheduling != 1) {
            return response([
                'message' => 'This is not a scheduling ticket.',
            ], 400);
        }

        $closedStageId = TicketStage::where('system_key', 'closed')->value('id');
        
        // Recupera tutti i ticket collegati a questa attività programmata
        $schedulingSlaves = $ticket->schedulingSlaves;
        
        // Conteggio totale
        $totalCount = $schedulingSlaves->count();
        
        // Separa aperti e chiusi
        $closedSlaves = $schedulingSlaves->where('stage_id', $closedStageId);
        $openSlaves = $schedulingSlaves->where('stage_id', '!=', $closedStageId);
        
        $closedCount = $closedSlaves->count();
        $openCount = $openSlaves->count();
        
        // Tempo totale dei ticket chiusi
        $totalClosedProcessingTime = $closedSlaves->sum('actual_processing_time') ?? 0;
        
        // Tempo totale dei ticket ancora aperti (tempo parziale)
        $totalOpenProcessingTime = $openSlaves->sum('actual_processing_time') ?? 0;
        
        $recapData = [
            'scheduled_duration' => $ticket->scheduled_duration,
            'total_slaves_count' => $totalCount,
            'closed_slaves_count' => $closedCount,
            'open_slaves_count' => $openCount,
            'total_closed_processing_time' => $totalClosedProcessingTime,
            'total_open_processing_time' => $totalOpenProcessingTime,
            'total_processing_time' => $totalClosedProcessingTime + $totalOpenProcessingTime,
            'scheduling_ticket_processing_time' => $ticket->actual_processing_time ?? 0,
        ];

        return response([
            'recap_data' => $recapData,
        ], 200);

    }

    public function report(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        $selectedCompanyId = $this->getSelectedCompanyId($user);

        if ($user['is_admin'] != 1 &&
            ($user['is_company_admin'] != 1 || ! $selectedCompanyId || $ticket->company_id != $selectedCompanyId)) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }
        //? Webform

        $webform_data = json_decode($ticket->messages()->first()->message);

        if (isset($webform_data->office)) {
            $companyForOffice = $ticket->company;
            if (method_exists($user, 'selectedCompany')) {
                $userSelectedCompany = $user->selectedCompany();
                $companyForOffice = $userSelectedCompany ?: $companyForOffice;
            }
            $office = $companyForOffice ? $companyForOffice->offices()->where('id', $webform_data->office)->first() : null;
            $webform_data->office = $office ? $office->name : null;
        } else {
            $webform_data->office = null;
        }

        if (isset($webform_data->referer)) {
            $referer = User::find($webform_data->referer);
            $webform_data->referer = $referer ? $referer->name.' '.$referer->surname : null;
        }

        if (isset($webform_data->referer_it)) {
            $referer_it = User::find($webform_data->referer_it);
            $webform_data->referer_it = $referer_it ? $referer_it->name.' '.$referer_it->surname : null;
        }

        $hardwareFields = $ticket->ticketType->typeFormField()->where('field_type', 'hardware')->pluck('field_label')->map(function ($field) {
            return strtolower($field);
        })->toArray();

        if (isset($webform_data)) {
            foreach ($webform_data as $key => $value) {
                if (in_array(strtolower($key), $hardwareFields)) {
                    // value è un array di id
                    foreach ($value as $index => $hardware_id) {
                        // Non è detto che l'hardware esista ancora. Se esiste si aggiungono gli altri valori
                        $hardware = $ticket->hardware()->where('hardware_id', $hardware_id)->first();
                        if ($hardware) {
                            $webform_data->$key[$index] = $hardware->id.' ('.$hardware->make.' '
                                .$hardware->model.' '.$hardware->serial_number
                                .($hardware->company_asset_number ? ' '.$hardware->company_asset_number : '')
                                .($hardware->support_label ? ' '.$hardware->support_label : '')
                                .')';
                        } else {
                            $webform_data->$key[$index] = $webform_data->$key[$index].' (assente)';
                        }
                    }
                }
            }
        }

        $ticket->ticketType->category = $ticket->ticketType->category->get();

        //? Avanzamento

        $avanzamento = [
            'attesa' => 0,
            'assegnato' => 0,
            'in_corso' => 0,
        ];

        // SE SI VUOLE USARE QUESTO CONTEGGIO VA RIVISTO IL METODO DI LOG DEI CAMBI DI STATO, PERCHÈ I NOMI SARANNO MODIFICABILI A PIACERE,
        // QUINDI NON SI PUÒ RICERCARE LA PAROLA NEL TESTO MA SERVE O UN CAMPO IN PIÙ O UNA TABELLA A PARTE.
        // HO AGGIUNTO I CAMPI NECESSARI NEGLI STATUS UPDATE, MA NON HO MODIFICATO IL CODICE CHE CREA GLI UPDATE (SIA MANUALI CHE AUTOMATICI).
        // POI SERVE IL CODICE RETROCOMPATIBILE PER I VECCHI TICKET O UNA MIGGRAZIONE CHE MODIFICHI GLI UPDATE ESISTENTI AGGIUNGENDO IL NUOVO CAMPO.
        // foreach ($ticket->statusUpdates as $update) {
        //     if ($update->type == 'status') {

        //         if (strpos($update->content, 'In attesa') !== false) {
        //             $avanzamento['attesa']++;
        //         }
        //         if (
        //             (strpos($update->content, 'Assegnato') !== false) || (strpos($update->content, 'assegnato') !== false)
        //         ) {
        //             $avanzamento['assegnato']++;
        //         }
        //         if (strpos($update->content, 'In corso') !== false) {
        //             $avanzamento['in_corso']++;
        //         }
        //     }
        // }

        //? Chiusura

        $closingMessage = '';

        $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->get();
        $closingUpdate = $closingUpdates->last();

        if ($closingUpdate) {
            $closingMessage = $closingUpdate->content;
        }

        $ticket->ticket_type = $ticket->ticketType ?? null;

        // ? Categoria

        $ticket['category'] = $ticket->ticketType->category()->first();

        // Nasconde i dati per gli admin se l'utente non è admin
        if ($user['is_admin'] != 1) {
            $ticket->setRelation('status_updates', null);
            $ticket->makeHidden(['admin_user_id', 'group_id', 'priority', 'is_user_error', 'actual_processing_time']);
        }

        $ticket['is_form_correct'] = $ticket->is_form_correct !== null ? $ticket->is_form_correct : 2;

        // ? Messaggi

        $ticket['messages'] = $ticket->messages()->with('user')->get();
        $author = $ticket->user()->first();
        if ($author->is_admin == 1) {
            $ticket['opened_by'] = 'Supporto';
        } else {
            $ticket['opened_by'] = $author->name.' '.$author->surname;
        }

        return response([
            'data' => $ticket,
            'webform_data' => $webform_data,
            'status_updates' => $avanzamento,
            'closing_messages' => $closingMessage,
            'isadmin' => $user['is_admin'],
        ], 200);
    }

    public function batchReport(Request $request)
    {

        $user = $request->user();
        if ($user['is_admin'] != 1 && $user['is_company_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        if ($request->useCache) {
            if ($user['is_admin'] == 1) {
                $cacheKey = 'admin_batch_report_'.$request->company_id.'_'.$request->from.'_'.$request->to.'_'.$request->type_filter;
            } else {
                $cacheKey = 'user_batch_report_'.$request->company_id.'_'.$request->from.'_'.$request->to.'_'.$request->type_filter;
            }

            if (Cache::has($cacheKey)) {
                $tickets_data = Cache::get($cacheKey);

                return response([
                    'data' => $tickets_data,
                ], 200);
            }
        }

        // Ticket che non sono ancora stati chiusi nel periodo selezionato

        // ignora i ticket creati dopo $request->to, escludi quelli con created_at dopo il to ,e quelli chiusi prima di $request->from

        $queryTo = \Carbon\Carbon::parse($request->to)->endOfDay()->toDateTimeString();

        $tickets = Ticket::where('company_id', $request->company_id)
            ->where('created_at', '<=', $queryTo)
            ->where('description', 'NOT LIKE', 'Ticket importato%')
            ->whereDoesntHave('statusUpdates', function ($query) use ($request) {
                $query->where('type', 'closing')
                    ->where('created_at', '<=', $request->from);
            })
            ->get();

        $filter = $request->type_filter;

        $tickets_data = [];

        foreach ($tickets as $ticket) {

            $ticket['category'] = $ticket->ticketType->category()->first();

            if (
                $filter == 'all' ||
                ($filter == 'request' && $ticket['category']['is_request'] == 1) ||
                ($filter == 'incident' && $ticket['category']['is_problem'] == 1)
            ) {

                if (! $ticket->messages()->first()) {
                    continue;
                }

                $webform_data = json_decode($ticket->messages()->first()->message);

                if (! $webform_data) {
                    continue;
                }

                if (isset($webform_data->office)) {
                    $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
                    $webform_data->office = $office ? $office->name : null;
                } else {
                    $webform_data->office = null;
                }

                if (isset($webform_data->referer)) {
                    $referer = User::find($webform_data->referer);
                    $webform_data->referer = $referer ? $referer->name.' '.$referer->surname : null;
                }

                if (isset($webform_data->referer_it)) {
                    $referer_it = User::find($webform_data->referer_it);
                    $webform_data->referer_it = $referer_it ? $referer_it->name.' '.$referer_it->surname : null;
                }

                $hardwareFields = $ticket->ticketType->typeFormField()->where('field_type', 'hardware')->pluck('field_label')->map(function ($field) {
                    return strtolower($field);
                })->toArray();

                if (isset($webform_data)) {
                    foreach ($webform_data as $key => $value) {
                        if (in_array(strtolower($key), $hardwareFields)) {
                            // value è un array di id
                            foreach ($value as $index => $hardware_id) {
                                // Non è detto che l'hardware esista ancora. Se esiste si aggiungono gli altri valori
                                $hardware = $ticket->hardware()->where('hardware_id', $hardware_id)->first();
                                if ($hardware) {
                                    $webform_data->$key[$index] = $hardware->id.' ('.$hardware->make.' '
                                        .$hardware->model.' '.$hardware->serial_number
                                        .($hardware->company_asset_number ? ' '.$hardware->company_asset_number : '')
                                        .($hardware->support_label ? ' '.$hardware->support_label : '')
                                        .')';
                                } else {
                                    $webform_data->$key[$index] = $webform_data->$key[$index].' (assente)';
                                }
                            }
                        }
                    }
                }

                //? Avanzamento

                $avanzamento = [
                    'attesa' => 0,
                    'assegnato' => 0,
                    'in_corso' => 0,
                ];

                // COME DETTO NELLA FUNZIONE REPORT,
                // SE SI VUOLE USARE QUESTO CONTEGGIO VA RIVISTO IL METODO DI LOG DEI CAMBI DI STATO, PERCHÈ I NOMI SARANNO MODIFICABILI A PIACERE,
                // QUINDI NON SI PUÒ RICERCARE LA PAROLA NEL TESTO MA SERVE O UN CAMPO IN PIÙ O UNA TABELLA A PARTE.
                // HO AGGIUNTO I CAMPI NECESSARI NEGLI STATUS UPDATE, MA NON HO MODIFICATO IL CODICE CHE CREA GLI UPDATE (SIA MANUALI CHE AUTOMATICI).
                // POI SERVE IL CODICE RETROCOMPATIBILE PER I VECCHI TICKET O UNA MIGGRAZIONE CHE MODIFICHI GLI UPDATE ESISTENTI AGGIUNGENDO IL NUOVO CAMPO.
                // foreach ($ticket->statusUpdates as $update) {
                //     if ($update->type == 'status') {

                //         if (strpos($update->content, 'In attesa') !== false) {
                //             $avanzamento['attesa']++;
                //         }
                //         if (
                //             (strpos($update->content, 'Assegnato') !== false) || (strpos($update->content, 'assegnato') !== false)
                //         ) {
                //             $avanzamento['assegnato']++;
                //         }
                //         if (strpos($update->content, 'In corso') !== false) {
                //             $avanzamento['in_corso']++;
                //         }
                //     }
                // }

                //? Chiusura

                $closingMessage = '';

                $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->get();
                $closingUpdate = $closingUpdates->last();

                if ($closingUpdate) {
                    $closingMessage = $closingUpdate->content;
                }

                $ticket->ticket_type = $ticket->ticketType ?? null;

                // Nasconde i dati per gli admin se l'utente non è admin
                if ($user['is_admin'] != 1) {

                    $ticket->setRelation('status_updates', null);
                    $ticket->makeHidden(['admin_user_id', 'group_id', 'priority', 'is_user_error', 'actual_processing_time']);
                }

                $ticket['messages'] = $ticket->messages()->with('user')->get();
                $author = $ticket->user()->first();
                if ($author->is_admin == 1) {
                    $ticket['opened_by'] = 'Supporto';
                } else {
                    $ticket['opened_by'] = $author->name.' '.$author->surname;
                }

                $tickets_data[] = [
                    'data' => $ticket,
                    'webform_data' => $webform_data,
                    'status_updates' => $avanzamento,
                    'closing_message' => [
                        'message' => $closingMessage,
                        'date' => $closingUpdate ? $closingUpdate->created_at : null,
                    ],

                ];
            }
        }

        if ($request->useCache) {
            $tickets_batch_data = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($tickets_data) {
                return $tickets_data;
            });
        } else {
            $tickets_batch_data = $tickets_data;
        }

        return response([
            'data' => $tickets_batch_data,
        ], 200);
    }

    public function search(Request $request)
    {

        $search = $request->query('q');

        $tickets = Ticket::query()->when($search, function (Builder $q, $value) {
            /**
             * @disregard Intelephense non rileva il metodo whereIn
             */
            return $q->whereIn('id', Ticket::search($value)->keys());
        })->with(['messages', 'company'])->get();

        $tickets_messages = TicketMessage::query()->when($search, function (Builder $q, $value) {
            /**
             * @disregard Intelephense non rileva il metodo whereIn
             */
            return $q->whereIn('id', TicketMessage::search($value)->keys());
        })->get();

        $ticket_ids_with_messages = $tickets_messages->pluck('ticket_id')->unique();
        $tickets_with_messages = Ticket::whereIn('id', $ticket_ids_with_messages)->with(['messages', 'company'])->get();
        $tickets = $tickets->merge($tickets_with_messages);

        $tickets = $tickets->map(function ($ticket) {

            $messages_map = $ticket->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                ];
            });

            return [
                'id' => $ticket->id,
                'ticket_opened_by' => $ticket->user->name.' '.$ticket->user->surname,
                'company' => $ticket->company->name,
                'description' => $ticket->description,
                'messages' => $messages_map,
            ];
        });

        return response()->json($tickets);
    }

    //? Nuove funzioni di assegnazione

    /**
     * La funzione di assegnazione riceve come parametri:
     * - ticket_id
     * - group_id
     * - user_id
     * - messaggio
     *
     * Una volta verificati i permessi dell'utente che fa la richiesta, crea un record in TicketAssignmentHistoryRecord con gli attuali dati del ticket.
     * Dopodichè aggiorna il ticket con i nuovi dati.
     * Infine crea un TicketStatusUpdate con il messaggio di assegnazione ed annessa notifica.
     * Se il ticket viene assegnato ad un utente, lo stato del ticket viene aggiornato in "Assegnato" se era "Nuovo".
     */
    public function assign(Request $request)
    {

        $request->validate([
            'ticket_id' => 'required|int|exists:tickets,id',
            'group_id' => 'required|int|exists:groups,id',
            'user_id' => 'required|int|exists:users,id',
            'message' => 'required|string',
        ]);

        $authUser = $request->user();
        if ($authUser['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $ticket = Ticket::where('id', $request->ticket_id)->first();

        if ($ticket == null) {
            return response([
                'message' => 'Ticket not found',
            ], 404);
        }

        // Crea un record di partenza nella history se non ce ne sono.

        if ($ticket->assignmentHistoryRecords()->count() == 0) {
            $historyRecord = TicketAssignmentHistoryRecord::create([
                'ticket_id' => $ticket->id,
                'group_id' => $ticket->group_id,
                'admin_user_id' => $ticket->admin_user_id,
                'message' => 'Record di partenza',
            ]);
        }

        $historyRecord = TicketAssignmentHistoryRecord::create([
            'ticket_id' => $ticket->id,
            'group_id' => $request->group_id,
            'admin_user_id' => $request->user_id,
            'message' => $request->message,
        ]);

        $ticket->update([
            'group_id' => $request->group_id ?? $ticket->group_id,
            'admin_user_id' => $request->user_id ?? $ticket->admin_user_id,
            'assigned' => true,
            'last_assignment_id' => $historyRecord->id,
        ]);

        $message = 'Assegnazione ticket all\'utente '.($request->user_id ? User::find($request->user_id)->name.' '.User::find($request->user_id)->surname : 'Nessuno').' del gruppo '.($request->group_id ? Group::find($request->group_id)->name : 'Nessuno').', con la motivazione: '.($request->message ?? 'Nessuna motivazione');

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $authUser->id,
            'content' => $message,
            'type' => 'assign',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Invalida la cache per chi ha creato il ticket e per i referenti.
        $ticket->invalidateCache();

        $oldStageId = $ticket->stage_id;
        // Se lo stato è 'Nuovo' aggiornarlo in assegnato
        $closedStageId = TicketStage::where('system_key', 'closed')->value('id');
        $newStageId = TicketStage::where('system_key', 'new')->value('id');
        $assignedStageId = TicketStage::where('system_key', 'assigned')->value('id');
        if ($ticket->stage_id == $newStageId && $request->user_id != null) {
            $ticket->update(['stage_id' => $assignedStageId]);
            $newStageText = TicketStage::find($ticket->stage_id)?->name ?? 'N/A';
            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $authUser->id,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $assignedStageId,
                'content' => 'Modifica automatica: Stato del ticket modificato in "'.$newStageText.'"',
                'type' => 'status',
            ]);
            // Invalida la cache per chi ha creato il ticket e per i referenti.
            $ticket->invalidateCache();
        }

        return response([
            'ticket' => $ticket,
            'assignment_history_record' => $historyRecord,
        ], 200);

    }

    /**
     * Recupera la history delle assegnazioni di un ticket.
     */
    public function asignmentHistory(Ticket $ticket)
    {

        $history = $ticket->assignmentHistoryRecords()->with([
            'adminUser',
            'group',
        ])->get();

        return response([
            'history' => $history,
        ], 200);

    }

    /**
     * La funzione di riassegnazione riceve come parametri:
     * - id della history a cui si deve tornare
     * - messaggio
     *
     * Dopo di che si assegna il ticket a utente e gruppo di quel record, si crea un nuovo record in history con i dati attuali del ticket e si crea un TicketStatusUpdate con il messaggio di riassegnazione.
     */
    public function reassign(Request $request)
    {

        $request->validate([
            'history_id' => 'required|int|exists:ticket_assignment_history_records,id',
            'message' => 'required|string',
        ]);

        $authUser = $request->user();

        if ($authUser['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $historyRecord = TicketAssignmentHistoryRecord::where('id', $request->history_id)->with([
            'adminUser',
            'group',
        ])->first();

        if ($historyRecord == null) {
            return response([
                'message' => 'History record not found',
            ], 404);
        }

        $ticket = Ticket::where('id', $historyRecord->ticket_id)->first();
        if ($ticket == null) {
            return response([
                'message' => 'Ticket not found',
            ], 404);
        }

        $newHistoryRecord = TicketAssignmentHistoryRecord::create([
            'ticket_id' => $ticket->id,
            'group_id' => $ticket->group_id,
            'admin_user_id' => $historyRecord->admin_user_id,
            'message' => $request->message,
        ]);

        $ticket->update([
            'group_id' => $historyRecord->group_id,
            'admin_user_id' => $historyRecord->admin_user_id,
            'assigned' => true,
            'last_assignment_id' => $newHistoryRecord->id,
        ]);

        $message = 'Riassegnazione ticket all\'utente '.($historyRecord->adminUser ? $historyRecord->adminUser->name.' '.$historyRecord->adminUser->surname : 'Nessuno').' del gruppo '.($historyRecord->group ? $historyRecord->group->name : 'Nessuno').', con la motivazione: '.($request->message ?? 'Nessuna motivazione');
        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $authUser->id,
            'content' => $message,
            'type' => 'assign',
        ]);

        dispatch(new SendUpdateEmail($update));

        // Invalida la cache per chi ha creato il ticket e per i referenti.
        $ticket->invalidateCache();

        return response([
            'ticket' => $ticket,
            'assignment_history_record' => $newHistoryRecord,
        ], 200);

    }

    public function getCurrentHandler(Ticket $ticket)
    {

        $admin_handler = null;
        if ($ticket->admin_user_id !== null) {
            $adminUser = User::select('id', 'name', 'surname')->find($ticket->admin_user_id);
            if ($adminUser) {
                $admin_handler = [
                    'id' => $adminUser->id,
                    'name' => trim($adminUser->name.' '.($adminUser->surname ?? '')),
                ];
            }
        }

        $group_handler = null;
        if ($ticket->group_id !== null) {
            $groupModel = Group::select('id', 'name')->find($ticket->group_id);
            if ($groupModel) {
                $group_handler = [
                    'id' => $groupModel->id,
                    'name' => $groupModel->name,
                ];
            }
        }

        return response([
            'handler' => [
                'admin' => $admin_handler,
                'group' => $group_handler,
            ],
        ], 200);
    }

    /**
     * Helper method per trovare un ticket con le relazioni necessarie
     */
    private function findTicketWithRelations($id)
    {
        return Ticket::where('id', $id)->with([
            'ticketType' => function ($query) {
                $query->with('category');
            },
            'hardware' => function ($query) {
                $query->with('hardwareType');
            },
            'company' => function ($query) {
                $query->select('id', 'name', 'logo_url');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'surname', 'email', 'is_admin');
            },
            'files',
        ])->first();
    }

    /**
     * Verifica se l'utente può visualizzare il ticket
     */
    private function canViewTicket($user, $ticket): bool
    {
        // Admin con accesso al gruppo del ticket
        if ($user->is_admin && $this->userBelongsToTicketGroup($user, $ticket)) {
            return true;
        }

        $selectedCompany = $user->selectedCompany();

        // Controlli per utenti senza company selezionata
        if (! $selectedCompany) {
            return $this->hasAssignmentHistory($user, $ticket) ||
                   $this->isReferer($user, $ticket) ||
                   $this->isDataOwner($user, $ticket);
        }

        // Controlli per utenti con company selezionata
        return ($this->isSameCompany($selectedCompany, $ticket) &&
                ($user->is_company_admin || $ticket->user_id === $user->id)) ||
               $this->hasAssignmentHistory($user, $ticket) ||
               $this->isReferer($user, $ticket) ||
               $this->isDataOwner($user, $ticket);
    }

    /**
     * Verifica se l'utente appartiene al gruppo del ticket
     */
    private function userBelongsToTicketGroup($user, $ticket): bool
    {
        return $user->groups->contains('id', $ticket->group_id);
    }

    /**
     * Verifica se l'utente ha uno storico di assegnazione per questo ticket
     */
    private function hasAssignmentHistory($user, $ticket): bool
    {
        return \App\Models\TicketAssignmentHistoryRecord::where('ticket_id', $ticket->id)
            ->where('admin_user_id', $user->id)
            ->exists();
    }

    /**
     * Verifica se l'utente è il referer del ticket
     */
    private function isReferer($user, $ticket): bool
    {
        return $ticket->referer && $ticket->referer->id === $user->id;
    }

    /**
     * Verifica se l'utente è il data owner dell'azienda del ticket
     */
    private function isDataOwner($user, $ticket): bool
    {
        return $ticket->company && $ticket->company->data_owner_email === $user->email;
    }

    /**
     * Verifica se il ticket appartiene alla stessa azienda dell'utente
     */
    private function isSameCompany($selectedCompany, $ticket): bool
    {
        return $ticket->company_id === $selectedCompany->id;
    }

    /**
     * Nasconde i dati del supporto se necessario
     */
    private function maskSupportUserIfNeeded($user, $ticket): void
    {
        if (! $user->is_admin && $ticket->user->is_admin) {
            $ticket->user->id = 1;
            $ticket->user->name = 'Supporto';
            $ticket->user->surname = '';
            $ticket->user->email = 'Supporto';
        }
    }

    /**
     * Segna i messaggi come letti
     */
    private function markMessagesAsRead($user, $ticket): void
    {
        if ($user->is_admin) {
            if (isset($ticket->admin_user_id) &&
                $ticket->admin_user_id === $user->id &&
                $ticket->unread_mess_for_adm > 0) {
                $ticket->update(['unread_mess_for_adm' => 0]);
                $this->clearUserCache($user);
            }
        } elseif ($ticket->unread_mess_for_usr > 0) {
            $ticket->update(['unread_mess_for_usr' => 0]);
            $this->clearUserCache($user);
        }
    }

    /**
     * Aggiunge campi virtuali per il frontend
     */
    private function addVirtualFields($ticket): void
    {
        $childTicket = Ticket::where('parent_ticket_id', $ticket->id)->first();
        $ticket->child_ticket_id = $childTicket->id ?? null;

        $reopenChildTicket = Ticket::where('reopen_parent_id', $ticket->id)->first();
        $ticket->reopen_child_ticket_id = $reopenChildTicket->id ?? null;

        $closingUpdate = $ticket->statusUpdates()
            ->where('type', 'closing')
            ->orderBy('created_at', 'desc')
            ->first();

        $ticket->closed_at = $closingUpdate->created_at ?? null;
        $ticket->can_reopen = $this->canReopenTicket($ticket, $closingUpdate, $childTicket);
    }

    /**
     * Verifica se il ticket può essere riaperto
     */
    private function canReopenTicket($ticket, $closingUpdate, $childTicket): bool
    {
        if (! $closingUpdate) {
            return false;
        }

        return $ticket->status == 5 &&
               (time() - strtotime($closingUpdate->created_at)) < (7 * 24 * 60 * 60) &&
               ! $childTicket;
    }

    /**
     * Pulisce la cache dell'utente
     */
    private function clearUserCache($user): void
    {
        cache()->forget('user_'.$user->id.'_tickets');
        cache()->forget('user_'.$user->id.'_tickets_with_closed');
    }

    /**
     * Gestisce operazioni null-safe per selectedCompany
     */
    private function getSelectedCompanyId($user)
    {
        $selectedCompany = $user->selectedCompany();

        return $selectedCompany ? $selectedCompany->id : null;
    }
}

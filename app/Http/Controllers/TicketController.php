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
use App\Models\TicketFile;
use App\Models\TicketMessage;
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

        // Generazione query in base ai parametri della richiesta.
        $query = Ticket::query()->where('company_id', $user->selectedCompany()->id);
        if (! $withClosed) {
            $query->where('status', '!=', 5);
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
        ]);

        // Generazione chiave cache in base all'utente e ai parametri della richiesta.
        if ($withClosed) {
            $cacheKey = 'user_'.$user->id.'_'.$user->selectedCompany()->id.'_tickets_with_closed';
        } else {
            $cacheKey = 'user_'.$user->id.'_'.$user->selectedCompany()->id.'_tickets';
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
            if ($ticketType->is_master && ($user->is_admin != 1)) {
                return response([
                    'message' => 'Only support admins can create master tickets.',
                ], 401);
            }

            // Admin o con ticketType.company_id tra le sue aziende
            if (! $user->is_admin && ($user->selectedCompany()->id != $ticketType->company_id)) {
                return response([
                    'message' => 'Unauthorized',
                ], 401);
            }

            $ticket = Ticket::create([
                'description' => $fields['description'],
                'type_id' => $fields['type_id'],
                'group_id' => $groupId,
                'user_id' => $user->id,
                'status' => '0',
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

                    // Chiude il ticket padre, segnala che il ticket procede in quello nuovo

                    $parentTicket->status = 5;
                    $parentTicket->is_rejected = 1;
                    $parentTicket->is_form_correct = 0;

                    $parentTicket->save();

                    TicketStatusUpdate::create([
                        'ticket_id' => $parentTicket->id,
                        'user_id' => $user->id,
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
                    // Se il ticket non è chiuso, non può essere riaperto.
                    if ($reopenedTicket->status != 5) {
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
                        if ($hardware) {
                            $ticket->hardware()->syncWithoutDetaching($id);
                            if (! in_array($id, $addedHardware)) {
                                $addedHardware[] = $id;
                            }
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
            'files',
        ])->first();

        if ($ticket == null) {
            return response([
                'message' => 'Ticket not found',
            ], 404);
        }

        // Se la richiesta è lato utente ed il ticket è stato aperto dal supporto, si deve nascondere il nome dell'utente che ha aperto il ticket
        if (! $user->is_admin && $ticket->user->is_admin) {
            $ticket->user->id = 1;
            $ticket->user->name = 'Supporto';
            $ticket->user->surname = '';
            $ticket->user->email = 'Supporto';
        }

        $groupIdExists = false;

        foreach ($user->groups as $group) {
            if ($group->id == $ticket['group_id']) {
                $groupIdExists = true;
                break;
            }
        }

        // Può avere il ticket solo se:
        // admin e del gruppo associato,
        // company admin e della stessa azienda del ticket,
        // della stessa azienda del ticket ed il creatore del ticket o se è l'utente interessato (referer), (non necessariamente company_admin).
        // titolare del dato dell'azienda del ticket.
        $authorized = false;
        // $isCompanyInUsersCompanies = in_array($ticket->company_id, $user->companies()->pluck('companies.id')->toArray());
        if (
            ($user['is_admin'] == 1 && $groupIdExists) ||
            (($ticket->company_id == $user->selectedCompany()->id) && $user['is_company_admin'] == 1) ||
            (($ticket->company_id == $user->selectedCompany()->id) && $ticket->user_id == $user->id) ||
            ($ticket->referer?->id == $user->id) ||
            ($ticket->company->data_owner_email == $user->email)
        ) {
            $authorized = true;
        }

        if (! $authorized) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Se l'utente è admin si devono impostare i messaggi degli utenti come letti, altrimenti si devono impostare i messaggi degli admin come letti.
        // Se si vuole mostrare quanti messaggi erano da leggere si potrebbe usare un async che posticipi l'azzeramento dei messaggi non letti, in modo da inviare le risposta prima della modifica.
        if ($user['is_admin'] == 1) {
            // $ticket->setUsersMessagesAsRead();
            // solo se l'admin è anche il gestore del ticket.
            if (isset($ticket->admin_user_id) && $ticket->admin_user_id == $user->id && $ticket->unread_mess_for_adm > 0) {
                $ticket->update(['unread_mess_for_adm' => 0]);
                cache()->forget('user_'.$user->id.'_tickets');
                cache()->forget('user_'.$user->id.'_tickets_with_closed');
            }
        } elseif ($ticket->unread_mess_for_usr > 0) {
            // $ticket->setAdminsMessagesAsRead();
            $ticket->update(['unread_mess_for_usr' => 0]);
            cache()->forget('user_'.$user->id.'_tickets');
            cache()->forget('user_'.$user->id.'_tickets_with_closed');
        }

        // Messo qui perchè altrimenti va in conflitto con gli update
        // (il campo child_ticket_id non esiste nel modello. Viene usato solo per la navigazione nel frontend)
        $childTicket = Ticket::where('parent_ticket_id', $ticket->id)->first();
        $ticket->child_ticket_id = $childTicket->id ?? null;

        // il campo reopen_child_ticket_id non esiste nel modello. viene usato solo per la navigazione nel frontend
        $reopenChildTicket = Ticket::where('reopen_parent_id', $ticket->id)->first();
        $ticket->reopen_child_ticket_id = $reopenChildTicket->id ?? null;

        $ticket->closed_at = null;
        // Il campo can reopen non esiste nel modello. Viene usato per indicare se il ticket può essere riaperto.
        $can_reopen = false;
        $closingUpdate = $ticket->statusUpdates()
            ->where('type', 'closing')
            ->orderBy('created_at', 'desc')
            ->first();
        if ($closingUpdate) {
            // Se il ticket è stato chiuso, non ha un ticket figlio (quindi non è stata usata l'altra funzione che chiude un ticket e ne apre un'altro)
            // e sono passati meno di 7 giorni dalla chiusura, si può riaprire.
            $can_reopen = ($ticket->status == 5
                && ((time() - strtotime($closingUpdate->created_at)) < (7 * 24 * 60 * 60))
                && ! $childTicket
            );

            // Aggiunge la data di chiusura per facilitare i controlli nel frontend.
            $ticket->closed_at = $closingUpdate->created_at ?? null;
        }
        $ticket->can_reopen = $can_reopen;

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

        $ticket = Ticket::where('id', $ticket->id)->where('user_id', $user->id)->first();
        cache()->forget('user_'.$user->id.'_tickets');
        cache()->forget('user_'.$user->id.'_tickets_with_closed');

        $ticket->update([
            'status' => '5',
        ]);
    }

    public function updateStatus(Ticket $ticket, Request $request)
    {
        $ticketStages = config('app.ticket_stages');

        // Controlla se lo status è presente nella richiesta e se è tra quelli validi.
        $request->validate([
            'status' => 'required|int|max:'.(count($ticketStages) - 1),
        ]);
        $isAdminRequest = $request->user()['is_admin'] == 1;

        if (! $isAdminRequest) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        // Se lo status della richiesta è uguale a quello attuale, non fa nulla.
        if ($ticket->status == $request->status) {
            return response([
                'message' => 'The ticket is already in this status.',
            ], 200);
        }

        // Se lo status corrisponde a "Chiuso", non permette la modifica.
        // si può chiudere solo usando l'apposita funzione di chiusura, che fa i controlli e richiede le informazioni necessarie.
        $index_status_chiuso = array_search('Chiuso', $ticketStages); // dovrebbe essere 5
        if ($request->status == $index_status_chiuso) {
            return response([
                'message' => 'It\'s not possible to close the ticket from here',
            ], 400);
        }

        // Se il ticket è chiuso, lo stato non può essere modificato.
        if ($ticket->status == $index_status_chiuso) {
            return response([
                'message' => 'The ticket is already closed. It cannot be modified.',
            ], 400);
        }

        $ticket->fill([
            'status' => $request->status,
            'wait_end' => $request['wait_end'],
        ])->save();

        $update = TicketStatusUpdate::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => 'Stato del ticket modificato in "'.$ticketStages[$request->status].'"',
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

        // $ticket->update([
        //     'status' => $request->status,
        // ]);
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

        if ($isValueChanged) {
            $ticket->save();

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => 'Ticket impostato come: '.($fields['is_billable'] ? 'Fatturabile' : 'Non fatturabile'),
                'type' => 'billing',
            ]);

            dispatch(new SendUpdateEmail($update));
        }

        return response([
            'ticket' => $ticket,
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

        // Se il valore è diverso da quello già esistente, lo aggiorna
        if ($ticket->actual_processing_time != $fields['actual_processing_time']) {
            // Controlli vari sul tempo e poi aggiornamento dati e registrazione modifica.

            // Il tempo deve essere maggiore di 0, un multiplo di 10 minuti e almeno uguale al tempo atteso, se impostato.
            if ($fields['actual_processing_time'] <= 0) {
                return response([
                    'message' => 'Actual processing time must be greater than 0.',
                ], 400);
            }
            if ($fields['actual_processing_time'] % 10 != 0) {
                return response([
                    'message' => 'Actual processing time must be a multiple of 10 minutes.',
                ], 400);
            }
            $ticketType = $ticket->ticketType;
            if ($ticketType->expected_processing_time && ($fields['actual_processing_time'] < $ticketType->expected_processing_time)) {
                return response([
                    'message' => 'Actual processing time must be greater than or equal to the expected processing time for this ticket type.',
                ], 400);
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
        $fields = $request->validate([
            'message' => 'required|string',
            'actualProcessingTime' => 'required|int',
            'workMode' => 'required|string',
            'isRejected' => 'required|boolean',
            'masterTicketId' => 'int|nullable',
            'no_user_response' => 'boolean',
        ]);

        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'Only admins can close tickets.',
            ], 401);
        }

        $ticketType = $ticket->ticketType;
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

        // Se viene indicato un master_id si controlla che questo ticket non sia master
        // e che il ticket con l'id indicato esista e sia master. quindi non può essere nemmeno l'id del ticket stesso.
        if ($request->masterTicketId) {
            if ($ticket->ticketType->is_master) {
                return response([
                    'message' => 'This is a master ticket. Master ticket cannot be slave.',
                ], 400);
            }
            $masterTicket = Ticket::where('id', $request->masterTicketId)
                ->whereHas('ticketType', function ($query) {
                    $query->where('is_master', true);
                })
                ->first();
            if (! $masterTicket) {
                return response([
                    'message' => 'Master ticket not found or not of master type.',
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

            $ticket->update([
                'status' => 5, // Si può impostare l'array di stati e prendere l'indice di "Chiuso" da lì
                'actual_processing_time' => $request->actualProcessingTime,
                'work_mode' => $request->workMode,
                'is_rejected' => $request->isRejected,
                'master_id' => $request->masterTicketId,
                'no_user_response' => $fields['no_user_response'] ?? false,
            ]);

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $authUser->id,
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
            $ticketStages = config('app.ticket_stages');
            $index_status_nuovo = array_search('Nuovo', $ticketStages);
            $index_status_chiuso = array_search('Chiuso', $ticketStages);
            if ($ticket->status != $index_status_nuovo && $ticket->status != $index_status_chiuso) {
                // $old_status = $ticketStages[$ticket->status];
                $ticket->update(['status' => $index_status_nuovo]);
                $new_status = $ticketStages[$ticket->status];

                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'content' => 'Modifica automatica: Stato del ticket modificato in "'.$new_status.'"',
                    'type' => 'status',
                ]);

                // Invalida la cache per chi ha creato il ticket e per i referenti.
                $ticket->invalidateCache();
            }
        }

        // Ticket va messo in attesa se si cambia il gruppo. Comportamento da confermare. --  Si è deciso di non metterlo in attesa.
        // Se deve ripartire da zero allora si può prendere la data della modifica come partenza, senza ulteriori cambi di stato.
        // $ticketStages = config('app.ticket_stages');

        // $index_in_attesa = array_search("In attesa", $ticketStages);
        // if ($ticket["status"] != $index_in_attesa){
        //     $ticket->update([
        //         'status' => $index_in_attesa
        //     ]);

        //     TicketStatusUpdate::create([
        //         'ticket_id' => $ticket->id,
        //         'user_id' => $request->user()->id,
        //         'content' => "Stato del ticket modificato in " . $index_in_attesa,
        //         'type' => 'status',
        //     ]);
        // }

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

        // Se lo stato è 'Nuovo' aggiornarlo in assegnato
        $ticketStages = config('app.ticket_stages');
        if ($ticketStages[$ticket->status] == 'Nuovo') {
            $index_status_assegnato = array_search('Assegnato', $ticketStages);
            $ticket->update(['status' => $index_status_assegnato]);
            $new_status = $ticketStages[$ticket->status];

            $update = TicketStatusUpdate::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'content' => 'Modifica automatica: Stato del ticket modificato in "'.$new_status.'"',
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
        if (! $authUser->is_admin && ($authUser->selectedCompany()->id != $ticket->company_id)) {
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

        /**
         * @disregard Intelephense non rileva il metodo mimeType
         */
        $url = Storage::disk('gcs')->temporaryUrl(
            $ticketFile->path,
            now()->addMinutes(65)
        );

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

        if ($withClosed) {
            $tickets = Ticket::whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        } else {
            $tickets = Ticket::where('status', '!=', 5)->whereIn('group_id', $groups->pluck('id'))->with('user')->get();
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show only the tickets belonging to the authenticated admin groups.
     */
    public function adminGroupsBillingTickets(Request $request)
    {
        // Se si vuole mostrare tutti i ticket a prescindere dal gruppo serve un superadmin. altrimenti si fanno vedere tutti e amen.
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $groups = $user->groups;

        $withClosed = $request->query('with-closed') == 'true' ? true : false;
        $withSet = $request->query('with-set') == 'true' ? true : false;

        if ($withClosed) {
            // $tickets = Ticket::whereIn('group_id', $groups->pluck('id'))->where('is_billable', null)->get();
            if ($withSet) {
                $tickets = Ticket::whereIn('group_id', $groups->pluck('id'))->get();
            } else {
                $tickets = Ticket::whereIn('group_id', $groups->pluck('id'))->where('is_billable', null)->get();
            }
        } else {
            // $tickets = Ticket::where("status", "!=", 5)->whereIn('group_id', $groups->pluck('id'))->where('is_billable', null)->get();
            if ($withSet) {
                $tickets = Ticket::where('status', '!=', 5)->whereIn('group_id', $groups->pluck('id'))->get();
            } else {
                $tickets = Ticket::where('status', '!=', 5)->whereIn('group_id', $groups->pluck('id'))->where('is_billable', null)->get();
            }
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Show closing messages of the ticket
     */
    public function closingMessages(Ticket $ticket, Request $request)
    {

        $user = $request->user();

        if ($user['is_admin'] != 1 && ($user->selectedCompany()->id != $ticket->company_id)) {
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
     * Get all slave tickets of a master ticket
     */
    public function getSlaveTickets(Ticket $ticket, Request $request)
    {

        $user = $request->user();

        if ($user['is_admin'] != 1 && ! ($user['is_company_admin'] == 1 && $ticket->company_id == $user->selectedCompany()->id)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $slaveTickets = $ticket->slaves;

        foreach ($slaveTickets as $slaveTicket) {
            $slaveTicket->setVisible([
                'id',
                'company_id',
                'status',
                'description',
                'group_id',
                'created_at',
                'type_id',
                'source',
                'parent_ticket_id',
                'master_id',
            ]);
            $slaveTicket->user_full_name =
                $slaveTicket->user->is_admin == 1
                ? ('Supporto'.($user['is_admin'] != 1 ? ' - '.$slaveTicket->user->id : ''))
                : ($slaveTicket->user->surname
                    ? $slaveTicket->user->surname.' '.strtoupper(substr($slaveTicket->user->name, 0, 1)).'.'
                    : $slaveTicket->user->name
                );
            $slaveTicket->makeVisible(['user_full_name']);

            $referer = $slaveTicket->referer;
            if ($referer) {
                $slaveTicket->referer_full_name =
                    $referer->surname
                    ? $referer->surname.' '.strtoupper(substr($referer->name, 0, 1)).'.'
                    : $referer->name;
                $slaveTicket->makeVisible(['referer_full_name']);
            }
        }

        return response([
            'slave_tickets' => $slaveTickets,
        ], 200);
    }

    public function report(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1 && ($user['is_company_admin'] != 1 || $ticket->company_id != $user->selectedCompany()->id)) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }
        //? Webform

        $webform_data = json_decode($ticket->messages()->first()->message);

        if (isset($webform_data->office)) {
            $selectedCompany = $ticket->company;
            if (method_exists($user, 'selectedCompany')) {
                $selectedCompany = $user->selectedCompany();
            }
            $office = $selectedCompany ? $selectedCompany->offices()->where('id', $webform_data->office)->first() : null;
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

        foreach ($ticket->statusUpdates as $update) {
            if ($update->type == 'status') {

                if (strpos($update->content, 'In attesa') !== false) {
                    $avanzamento['attesa']++;
                }
                if (
                    (strpos($update->content, 'Assegnato') !== false) || (strpos($update->content, 'assegnato') !== false)
                ) {
                    $avanzamento['assegnato']++;
                }
                if (strpos($update->content, 'In corso') !== false) {
                    $avanzamento['in_corso']++;
                }
            }
        }

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

                foreach ($ticket->statusUpdates as $update) {
                    if ($update->type == 'status') {

                        if (strpos($update->content, 'In attesa') !== false) {
                            $avanzamento['attesa']++;
                        }
                        if (
                            (strpos($update->content, 'Assegnato') !== false) || (strpos($update->content, 'assegnato') !== false)
                        ) {
                            $avanzamento['assegnato']++;
                        }
                        if (strpos($update->content, 'In corso') !== false) {
                            $avanzamento['in_corso']++;
                        }
                    }
                }

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
}

<?php

namespace App\Http\Controllers;

use App\Jobs\SendNewMessageEmail;
use App\Mail\TicketMessageMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatusUpdate;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TicketMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($ticket_id, Request $request)
    {
        // 

        $ticket = Ticket::where('id', $ticket_id)->get()->first();

        if(!$ticket) {
            return response([
                'message' => 'Ticket not found'
            ], 404);
        }

        // $tickemessages = TicketMessage::where('ticket_id', $ticket_id)->with(['user'])->get();
        // Prendere dagli utenti solo i dati che si possono mostrare
        $tickemessages = TicketMessage::where('ticket_id', $ticket_id)->with([
            'user' => function ($query) {$query->select(
                ['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']
            );},
            'user.companies:id,name'
        ])->get();
        
        // Se la richiesta è di un utente nascondere i dati degli admin
        if(!$request->user()->is_admin) {
            foreach ($tickemessages as $message) {
                if ($message->user->is_admin) {
                    $message->user->id = 1;
                    $message->user->name = "Supporto";
                    $message->user->surname = "";
                    $message->user->email = "Supporto";
                }
            }
        }

        return response([
            'ticket_messages' => $tickemessages,
        ], 200);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        return response([
            'message' => 'Please use /api/store to create a new message',
        ], 404);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store($id, Request $request)
    {
        $user = $request->user();

        $fields = $request->validate([
            'message' => 'required|string',
        ]);

        $ticket_message = TicketMessage::create([
            'message' => $fields['message'],
            'ticket_id' => $id,
            'user_id' => $user->id,
        ]);

        $ticket = Ticket::where('id', $id)->with(['ticketType' => function ($query) {
            $query->with('category');
        }])->first();

        $brand_url = $ticket->brandUrl();

        $ticketStages = config('app.ticket_stages');

        if($user['is_admin'] == 1) {
            $ticket->update(['unread_mess_for_usr' => ($ticket->unread_mess_for_usr + 1)]);

            // A messaggio da admin modificare lo stato in 'In corso', se lo stato è 'Nuovo' o 'Assegnato' ed assegnarlo a chi invia il messaggio se non è assegnato.
            $index_status_nuovo = array_search("Nuovo", $ticketStages);
            $index_status_assegnato = array_search("Assegnato", $ticketStages);
            if($ticket->status == $index_status_nuovo || $ticket->status == $index_status_assegnato){
                $index_status_in_corso = array_search("In corso", $ticketStages);
                
                $old_status = $ticketStages[$ticket->status];
                $ticket->update(['status' => $index_status_in_corso]);
                $new_status = $ticketStages[$ticket->status];
                
                $sentence = 'Modifica automatica: Stato del ticket modificato in "' . $new_status . '"';
                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'content' => $sentence,
                    'type' => 'status',
                ]);
            }

            if(
                !$ticket->admin_user_id 
            ){
                $ticket->update(['admin_user_id' => $user->id]);

                $update = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'content' => "Modifica automatica: Ticket assegnato all'utente " . $user->name . " " . $user->surname ?? "",
                    'type' => 'assign',
                ]);
            }

            // $ticket_message->is_read = 1;
            // $ticket_message->save();

            // Questa parte andrà modificata con la modifica dei referer
            $ticket->invalidateCache();

        } else {
            $ticket->update(['unread_mess_for_adm' => ($ticket->unread_mess_for_adm + 1)]);
            $index_status_attesa_feedback = array_search("Attesa feedback cliente", $ticketStages);
            if ($ticket->status == $index_status_attesa_feedback) {
                $index_status_in_corso = array_search("In corso", $ticketStages);

                $old_status = $ticketStages[$ticket->status];
                $ticket->update(['status' => $index_status_in_corso]);
                $new_status = $ticketStages[$ticket->status];

                $sentence = 'Modifica automatica: Stato del ticket modificato in "' . $new_status . '"';
                TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'content' => $sentence,
                    'type' => 'status',
                ]);
            }
        }
        
        dispatch(new SendNewMessageEmail($ticket, $user, $ticket_message->message, $brand_url));

        return response([
            'ticket_message' => $ticket_message,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketMessage $ticketMesage)
    {
        //Not allowed 

        return response([
            'message' => 'Not allowed',
        ], 404);


    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketMessage $ticketMesage)
    {
        //

        return response([
            'message' => 'Not allowed',
        ], 404);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketMessage $ticketMesage)
    {
        //

        // $fields = $request->validate([
        //     'is_read' => 'required|boolean',
        // ]);

        // $ticket_message = TicketMessage::where('id', $ticketMesage->id)->first();

        // if(!$ticket_message) {
        //     return response([
        //         'message' => 'Ticket message not found'
        //     ], 404);
        // }

        // $ticket_message->is_read = $fields['is_read'];

        // $ticket_message->save();

        // return response([
        //     'ticket_message' => $ticket_message,
        // ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketMessage $ticketMesage)
    {
        //

        $ticket_message = TicketMessage::where('id', $ticketMesage->id)->where('user_id', auth()->id())->first();

        if(!$ticket_message) {
            return response([
                'message' => 'Ticket message not found'
            ], 404);
        }

        $ticket_message->delete();

        return response([
            'message' => 'Ticket message deleted'
        ], 200);
    }
}

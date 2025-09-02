<?php

namespace App\Http\Controllers;

use App\Models\OldTicket;
use App\Models\OldTicketMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Database\Eloquent\Builder;

class OldTicketController extends Controller {
    //

    public function import() {

        $old_database_connect = DB::connection('old_gest');

        $aziende_filter = [
            'Labor Medical Srl',
            'Labor Project Srl',
            'ASSALCO',
            'Labor Formazione',
            'Biemme Adesivi S.r.l.',
            'Retesalute'
        ];

        $old_tickets = $old_database_connect->table('tickets')->whereIn('azienda', $aziende_filter)->limit(100)->get();

        foreach ($old_tickets as $key => $value) {

            $formatted_closed_at = Carbon::parse($value->cdate)->format('Y-m-d');

            $oldticket = OldTicket::create([
                'old_ticket_id' => $value->tid,
                'business_name' => $value->azienda,
                'opened_by' => $value->persona,
                'ticket_type' => $value->obj,
                'opened_at' => $value->rdate,
                'closed_at' => $formatted_closed_at,
                'closing_notes' => $value->commento,
            ]);

            $ticket_id = $oldticket->id;

            $messages = $old_database_connect->table('tickets_messages')->where('tid', $value->tid)->get();

            foreach ($messages as $message) {

                $is_admin = $message->peid != null;

                if ($is_admin) {
                    $sender = $old_database_connect->table('personale')->where('peid', $message->peid)->first();
                    $sender_name = $sender->name . ' ' . $sender->surname;
                } else {
                    $sender = $old_database_connect->table('support_users')->where('supid', $message->supid)->first();
                    if ($sender) {
                        $sender_name = $sender->nome . ' ' . $sender->cognome;
                    } else {
                        $sender_name = 'Non definito';
                    }
                }


                $old_message = OldTicketMessage::create([
                    'old_ticket_id' => $message->tid,
                    'sender' => $sender_name,
                    'message' => $message->message,
                    'sent_at' => $message->rdate,
                    'is_admin' => $is_admin
                ]);
            }
        }
    }

    public function search(Request $request) {

        $search = $request->input('q');

        $old_tickets = OldTicket::query()
            ->when($search, function (Builder $q, $value) {
                /** 
                 * @disregard Intelephense non rileva il metodo whereIn
                 */
                return $q->whereIn('id', OldTicket::search($value)->keys());
            })->with(['messages'])->get();

        $old_ticket_messages = OldTicketMessage::query()
            ->when($search, function (Builder $q, $value) {
                /** 
                 * @disregard Intelephense non rileva il metodo whereIn
                 */
                return $q->whereIn('id', OldTicketMessage::search($value)->keys());
            })->get();

        $ticket_ids_with_messages = $old_ticket_messages->pluck('old_ticket_id')->unique();
        $old_tickets_with_messages = OldTicket::whereIn('old_ticket_id', $ticket_ids_with_messages)->with(['messages'])->get();
        $old_tickets = $old_tickets->merge($old_tickets_with_messages);

        $old_tickets = $old_tickets->unique('old_ticket_id')->values();



        return response()->json($old_tickets);
    }
}

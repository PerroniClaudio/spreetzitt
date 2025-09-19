<?php

namespace App\Http\Controllers;

use App\Models\TicketReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketReminderController extends Controller
{
    /**
     * Display a listing of all ticket reminders.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $reminders = TicketReminder::with(['user', 'ticket'])
            ->where('user_id', $user->id)
            ->orderBy('reminder_date', 'asc')
            ->get();

        return response([
            'reminders' => $reminders,
        ], 200);
    }

    /**
     * Store a newly created reminder in storage.
     */
    public function store(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'message' => 'required|string|max:1000',
            'reminder_date' => 'required|date|after:now',
            'create_outlook_event' => 'nullable|boolean',

        ]);

        try {
            // Crea il reminder nel database
            $reminder = TicketReminder::create([
                'event_uuid' => Str::uuid(),
                'user_id' => $user->id,
                'ticket_id' => $validated['ticket_id'],
                'message' => $validated['message'],
                'reminder_date' => $validated['reminder_date'],
            ]);

            $reminder->load(['user', 'ticket']);

            // Se richiesto, crea anche l'evento Outlook
            if ($validated['create_outlook_event'] ?? false) {
                $outlookResult = $this->createOutlookEvent($user, $reminder, $validated);

                if ($outlookResult['success']) {
                    return response([
                        'reminder' => $reminder,
                        'outlook_event' => $outlookResult['event'],
                        'message' => 'Reminder e evento Outlook creati con successo',
                    ], 201);
                } else {
                    return response([
                        'reminder' => $reminder,
                        'message' => 'Reminder creato ma errore nella creazione dell\'evento Outlook',
                        'error' => $outlookResult['error'],
                    ], 207); // 207 Multi-Status
                }
            }

            return response([
                'reminder' => $reminder,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Errore durante la creazione del reminder', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response([
                'message' => 'Errore durante la creazione del reminder: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the latest reminder for a specific ticket.
     */
    public function getLatestByTicket(Request $request, int $ticketId): Response
    {
        $user = $request->user();

        $latestReminder = TicketReminder::with(['user', 'ticket'])
            ->where('ticket_id', $ticketId)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $latestReminder) {
            return response([
                'message' => 'Nessun reminder trovato per questo ticket',
                'reminder' => null,
            ], 200);
        }

        return response([
            'reminder' => $latestReminder,
        ], 200);
    }

    /**
     * Create event in Outlook calendar using Microsoft Graph API.
     */
    private function createOutlookEvent($user, TicketReminder $reminder, array $validated): array
    {
        if (! $user->microsoft_access_token) {
            return [
                'success' => false,
                'error' => 'Microsoft access token non disponibile per questo utente. Effettuare nuovamente il login con Microsoft.',
            ];
        }

        try {
            // Prepara i dati per l'evento Outlook
            $startTime = \Carbon\Carbon::parse($reminder->reminder_date);
            $endTime = $startTime->copy()->addMinutes(30); // Durata fissa 30 minuti

            $eventData = [
                'subject' => 'Ticket Reminder: '.($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id),
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $this->buildEventBody($reminder),
                ],
                'start' => [
                    'dateTime' => $startTime->toISOString(),
                    'timeZone' => config('app.timezone', 'UTC'),
                ],
                'end' => [
                    'dateTime' => $endTime->toISOString(),
                    'timeZone' => config('app.timezone', 'UTC'),
                ],
                'isReminderOn' => true,
                'reminderMinutesBeforeStart' => 15,
                'categories' => ['Ticket Support'],
            ];

            // Chiama Microsoft Graph API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$user->microsoft_access_token,
                'Content-Type' => 'application/json',
            ])->post('https://graph.microsoft.com/v1.0/me/events', $eventData);

            if ($response->successful()) {
                $outlookEvent = $response->json();

                // Aggiorna il reminder con l'ID dell'evento Outlook
                $reminder->update([
                    'event_uuid' => $outlookEvent['id'] ?? $reminder->event_uuid,
                ]);

                return [
                    'success' => true,
                    'event' => $outlookEvent,
                ];
            } else {
                // Log dell'errore
                Log::error('Errore creazione evento Outlook', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json(),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Errore durante la creazione dell\'evento Outlook', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate and download ICS calendar file for reminders.
     */
    public function generateIcs(Request $request): Response
    {
        $user = $request->user();

        $reminders = TicketReminder::with(['ticket'])
            ->where('user_id', $user->id)
            ->where('reminder_date', '>=', now())
            ->orderBy('reminder_date', 'asc')
            ->get();

        $icsContent = $this->buildIcsContent($reminders);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ticket-reminders.ics"',
        ]);
    }

    /**
     * Build ICS calendar content from reminders.
     */
    private function buildIcsContent($reminders): string
    {
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Spreetzitt//Ticket Reminders//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";

        foreach ($reminders as $reminder) {
            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= 'UID:'.$reminder->event_uuid."\r\n";
            $ics .= 'DTSTAMP:'.now()->format('Ymd\THis\Z')."\r\n";
            $ics .= 'DTSTART:'.$reminder->reminder_date->format('Ymd\THis\Z')."\r\n";
            $ics .= 'SUMMARY:Ticket Reminder: '.$this->escapeLine($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id)."\r\n";
            $ics .= 'DESCRIPTION:'.$this->escapeLine($reminder->message)."\r\n";
            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= "BEGIN:VALARM\r\n";
            $ics .= "TRIGGER:-PT15M\r\n";
            $ics .= "ACTION:DISPLAY\r\n";
            $ics .= "DESCRIPTION:Ticket Reminder\r\n";
            $ics .= "END:VALARM\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build HTML content for Outlook event body.
     */
    private function buildEventBody(TicketReminder $reminder): string
    {
        $ticketUrl = config('app.url').'/ticket/'.$reminder->ticket_id;

        return "
            <div>
                <h3>Ticket Reminder</h3>
                <p><strong>Messaggio:</strong> {$reminder->message}</p>
                <p><strong>Ticket ID:</strong> #{$reminder->ticket_id}</p>
                <p><strong>Descrizione Ticket:</strong> ".($reminder->ticket->description ?? 'N/A')."</p>
                <p><strong>Data Reminder:</strong> {$reminder->reminder_date->format('d/m/Y H:i')}</p>
                <p><a href=\"{$ticketUrl}\">Visualizza Ticket</a></p>
            </div>
        ";
    }

    /**
     * Escape special characters for ICS format.
     */
    private function escapeLine(string $text): string
    {
        $text = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', ''], $text);

        return substr($text, 0, 75); // Limit length for ICS compatibility
    }
}

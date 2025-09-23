<?php

namespace App\Http\Controllers;

use App\Models\TicketReminder;
use App\Models\TicketStage;
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
            'is_ticket_deadline' => 'nullable|boolean',
        ]);

        try {
            // Crea il reminder nel database
            $reminder = TicketReminder::create([
                'event_uuid' => Str::uuid(),
                'user_id' => $user->id,
                'ticket_id' => $validated['ticket_id'],
                'message' => $validated['message'],
                'reminder_date' => $validated['reminder_date'],
                'is_ticket_deadline' => $validated['is_ticket_deadline'] ?? false,
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

    public function listForTicket(Request $request, int $ticketId): Response
    {
        $user = $request->user();

        $reminders = TicketReminder::with(['user', 'ticket'])
            ->where('ticket_id', $ticketId)
            ->where('user_id', $user->id)
            ->orderBy('reminder_date', 'asc')
            ->get();

        return response([
            'reminders' => $reminders,
        ], 200);
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
     * Get all deadline reminders for the authenticated user.
     */
    public function getDeadlineReminders(Request $request): Response
    {
        $user = $request->user();

        $deadlineReminders = TicketReminder::with(['user', 'ticket'])
            ->where('user_id', $user->id)
            ->where('is_ticket_deadline', true)
            ->orderBy('reminder_date', 'asc')
            ->get();

        return response([
            'deadline_reminders' => $deadlineReminders,
        ], 200);
    }

    /**
     * Get deadline reminder for a specific ticket.
     */
    public function getDeadlineByTicket(Request $request, int $ticketId): Response
    {
        $user = $request->user();

        $deadlineReminder = TicketReminder::with(['user', 'ticket'])
            ->where('ticket_id', $ticketId)
            ->where('user_id', $user->id)
            ->where('is_ticket_deadline', true)
            ->first();

        if (! $deadlineReminder) {
            return response([
                'message' => 'Nessun reminder di scadenza trovato per questo ticket',
                'deadline_reminder' => null,
            ], 200);
        }

        return response([
            'deadline_reminder' => $deadlineReminder,
        ], 200);
    }

    /**
     * Display the specified reminder.
     */
    public function show(Request $request, TicketReminder $reminder): Response
    {
        $user = $request->user();

        // Verifica che il reminder appartenga all'utente
        if ($reminder->user_id !== $user->id) {
            return response([
                'message' => 'Non autorizzato a visualizzare questo reminder',
            ], 403);
        }

        $reminder->load(['user', 'ticket']);

        return response([
            'reminder' => $reminder,
        ], 200);
    }

    /**
     * Get tickets with deadline reminders in the current month.
     */
    public function getTicketsWithDeadlinesThisMonth(Request $request): Response
    {
        $user = $request->user();

        // Data inizio e fine del mese corrente
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Query per reminder di scadenza nel mese corrente (solo futuri)
        $deadlineReminders = TicketReminder::with([
            'user',
            'ticket.ticketType',
            'ticket.company',
            'ticket.handler', // Questo è il gestore (admin_user_id)
        ])
            ->where('user_id', $user->id)
            ->where('is_ticket_deadline', true)
            ->where('reminder_date', '>=', now()) // Solo nel futuro
            ->whereYear('reminder_date', now()->year)
            ->whereMonth('reminder_date', now()->month)
            ->orderBy('reminder_date', 'asc')
            ->get();

        // Raggruppa per ticket per evitare duplicati
        $ticketsWithDeadlines = $deadlineReminders->groupBy('ticket_id')->map(function ($reminders) {
            $earliestReminder = $reminders->sortBy('reminder_date')->first();
            $ticket = $earliestReminder->ticket;

            return [
                'id' => $ticket->id,
                'tipo' => [
                    'nome' => $ticket->ticketType->name ?? 'N/A',
                    'id' => $ticket->type_id ?? null,
                ],
                'stato' => TicketStage::find($ticket->stage_id)?->name ?? 'N/A',
                'id_stato' => $ticket->stage_id,
                'azienda' => $ticket->company->name ?? 'N/A',
                'gestore' => $ticket->handler ? [
                    'id' => $ticket->handler->id,
                    'nome' => $ticket->handler->name,
                    'email' => $ticket->handler->email,
                ] : null,
                'data_scadenza' => $earliestReminder->reminder_date,
                'days_until_deadline' => now()->diffInDays($earliestReminder->reminder_date),
            ];
        })->values();

        return response([
            'tickets_with_deadlines' => $ticketsWithDeadlines,
            'total_count' => $ticketsWithDeadlines->count(),
            'month' => now()->format('F Y'),
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

            $eventTitle = $reminder->is_ticket_deadline
                ? '🚨 SCADENZA: '.($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id)
                : 'Ticket Reminder: '.($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id);

            $categories = $reminder->is_ticket_deadline
                ? ['Ticket Support', 'Deadline', 'Urgent']
                : ['Ticket Support'];

            $eventData = [
                'subject' => $eventTitle,
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
                'reminderMinutesBeforeStart' => $reminder->is_ticket_deadline ? 30 : 15, // Più avviso per scadenze
                'categories' => $categories,
                'importance' => $reminder->is_ticket_deadline ? 'high' : 'normal',
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
            $isDeadline = $reminder->is_ticket_deadline;
            $title = $isDeadline ? 'SCADENZA: ' : 'Ticket Reminder: ';
            $priority = $isDeadline ? "PRIORITY:1\r\n" : '';

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= 'UID:'.$reminder->event_uuid."\r\n";
            $ics .= 'DTSTAMP:'.now()->format('Ymd\THis\Z')."\r\n";
            $ics .= 'DTSTART:'.$reminder->reminder_date->format('Ymd\THis\Z')."\r\n";
            $ics .= 'SUMMARY:'.$title.$this->escapeLine($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id)."\r\n";
            $ics .= 'DESCRIPTION:'.$this->escapeLine($reminder->message.($isDeadline ? ' [SCADENZA TICKET]' : ''))."\r\n";
            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= $priority;
            $ics .= "BEGIN:VALARM\r\n";
            $ics .= "TRIGGER:-PT15M\r\n";
            $ics .= "ACTION:DISPLAY\r\n";
            $ics .= 'DESCRIPTION:'.($isDeadline ? 'SCADENZA TICKET' : 'Ticket Reminder')."\r\n";
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
        $reminderType = $reminder->is_ticket_deadline ? '🚨 SCADENZA TICKET' : 'Ticket Reminder';

        return "
            <div>
                <h3>{$reminderType}</h3>
                <p><strong>Messaggio:</strong> {$reminder->message}</p>
                <p><strong>Ticket ID:</strong> #{$reminder->ticket_id}</p>
                <p><strong>Descrizione Ticket:</strong> ".($reminder->ticket->description ?? 'N/A')."</p>
                <p><strong>Data Reminder:</strong> {$reminder->reminder_date->format('d/m/Y H:i')}</p>
                ".($reminder->is_ticket_deadline ? '<p><strong>⚠️ QUESTO È UN REMINDER DI SCADENZA</strong></p>' : '')."
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

    /**
     * Update the specified reminder.
     */
    public function update(Request $request, TicketReminder $reminder): Response
    {
        $user = $request->user();

        // Verifica che il reminder appartenga all'utente
        if ($reminder->user_id !== $user->id) {
            return response([
                'message' => 'Non autorizzato a modificare questo reminder',
            ], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'reminder_date' => 'required|date|after:now',
            'is_ticket_deadline' => 'nullable|boolean',
            'sync_outlook' => 'nullable|boolean',
        ]);

        try {
            // Aggiorna il reminder nel database
            $reminder->update([
                'message' => $validated['message'],
                'reminder_date' => $validated['reminder_date'],
                'is_ticket_deadline' => $validated['is_ticket_deadline'] ?? $reminder->is_ticket_deadline,
            ]);

            $reminder->load(['user', 'ticket']);

            // Se richiesto e se l'utente ha token Microsoft, aggiorna anche su Outlook
            if (($validated['sync_outlook'] ?? false) && $user->microsoft_access_token) {
                $outlookResult = $this->updateOutlookEvent($user, $reminder);

                if ($outlookResult['success']) {
                    return response([
                        'reminder' => $reminder,
                        'outlook_event' => $outlookResult['event'],
                        'message' => 'Reminder e evento Outlook aggiornati con successo',
                    ], 200);
                } else {
                    return response([
                        'reminder' => $reminder,
                        'message' => 'Reminder aggiornato ma errore nell\'aggiornamento dell\'evento Outlook',
                        'error' => $outlookResult['error'],
                    ], 207); // 207 Multi-Status
                }
            }

            return response([
                'reminder' => $reminder,
                'message' => 'Reminder aggiornato con successo',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Errore durante l\'aggiornamento del reminder', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            return response([
                'message' => 'Errore durante l\'aggiornamento del reminder: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified reminder from storage.
     */
    public function destroy(Request $request, TicketReminder $reminder): Response
    {
        $user = $request->user();

        // Verifica che il reminder appartenga all'utente
        if ($reminder->user_id !== $user->id) {
            return response([
                'message' => 'Non autorizzato a eliminare questo reminder',
            ], 403);
        }

        $syncOutlook = $request->boolean('sync_outlook', false);

        try {
            // Se richiesto e se l'utente ha token Microsoft, elimina anche da Outlook
            if ($syncOutlook && $user->microsoft_access_token) {
                $outlookResult = $this->deleteOutlookEvent($user, $reminder);

                if (! $outlookResult['success']) {
                    // Log l'errore ma continua con l'eliminazione dal database
                    Log::warning('Errore durante l\'eliminazione dell\'evento Outlook', [
                        'user_id' => $user->id,
                        'reminder_id' => $reminder->id,
                        'error' => $outlookResult['error'],
                    ]);
                }
            }

            // Elimina il reminder dal database
            $reminder->delete();

            return response([
                'message' => 'Reminder eliminato con successo',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Errore durante l\'eliminazione del reminder', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            return response([
                'message' => 'Errore durante l\'eliminazione del reminder: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update event in Outlook calendar using Microsoft Graph API.
     */
    private function updateOutlookEvent($user, TicketReminder $reminder): array
    {
        if (! $user->microsoft_access_token) {
            return [
                'success' => false,
                'error' => 'Microsoft access token non disponibile per questo utente.',
            ];
        }

        try {
            // Prepara i dati aggiornati per l'evento Outlook
            $startTime = \Carbon\Carbon::parse($reminder->reminder_date);
            $endTime = $startTime->copy()->addMinutes(30); // Durata fissa 30 minuti

            $eventTitle = $reminder->is_ticket_deadline
                ? 'SCADENZA: '.($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id)
                : 'Ticket Reminder: '.($reminder->ticket->description ?? 'Ticket #'.$reminder->ticket_id);

            $categories = $reminder->is_ticket_deadline
                ? ['Ticket Support', 'Deadline', 'Urgent']
                : ['Ticket Support'];

            $eventData = [
                'subject' => $eventTitle,
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
                'reminderMinutesBeforeStart' => $reminder->is_ticket_deadline ? 30 : 15,
                'categories' => $categories,
                'importance' => $reminder->is_ticket_deadline ? 'high' : 'normal',
            ];

            // Aggiorna l'evento su Microsoft Graph API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$user->microsoft_access_token,
                'Content-Type' => 'application/json',
            ])->patch('https://graph.microsoft.com/v1.0/me/events/'.$reminder->event_uuid, $eventData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'event' => $response->json(),
                ];
            } else {
                // Se l'evento non esiste su Outlook (404), considera come successo parziale
                if ($response->status() === 404) {
                    Log::warning('Evento Outlook non trovato durante l\'aggiornamento (probabilmente eliminato manualmente)', [
                        'user_id' => $user->id,
                        'reminder_id' => $reminder->id,
                        'event_uuid' => $reminder->event_uuid,
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Evento non trovato su Outlook (potrebbe essere stato eliminato manualmente)',
                    ];
                }

                // Log dell'errore
                Log::error('Errore aggiornamento evento Outlook', [
                    'user_id' => $user->id,
                    'reminder_id' => $reminder->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json(),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Errore durante l\'aggiornamento dell\'evento Outlook', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete event from Outlook calendar using Microsoft Graph API.
     */
    private function deleteOutlookEvent($user, TicketReminder $reminder): array
    {
        if (! $user->microsoft_access_token) {
            return [
                'success' => false,
                'error' => 'Microsoft access token non disponibile per questo utente.',
            ];
        }

        try {
            // Elimina l'evento da Microsoft Graph API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$user->microsoft_access_token,
            ])->delete('https://graph.microsoft.com/v1.0/me/events/'.$reminder->event_uuid);

            if ($response->successful()) {
                return [
                    'success' => true,
                ];
            } else {
                // Se l'evento non esiste su Outlook (404), considera come successo
                if ($response->status() === 404) {
                    Log::info('Evento Outlook non trovato durante l\'eliminazione (probabilmente già eliminato)', [
                        'user_id' => $user->id,
                        'reminder_id' => $reminder->id,
                        'event_uuid' => $reminder->event_uuid,
                    ]);

                    return [
                        'success' => true, // Consideriamo come successo perché l'obiettivo è eliminarlo
                    ];
                }

                // Log dell'errore
                Log::error('Errore eliminazione evento Outlook', [
                    'user_id' => $user->id,
                    'reminder_id' => $reminder->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json(),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Errore durante l\'eliminazione dell\'evento Outlook', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

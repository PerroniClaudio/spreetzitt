<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePdfReport;
use App\Models\Company;
use App\Models\TicketReportPdfExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketReportPdfExportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Lista per company singola
     */
    public function pdfCompany(Company $company, Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = TicketReportPdfExport::where('company_id', $company->id)
            // ->where('is_generated', true)
            ->orderBy('start_date', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }
    
    /**
     * Lista per company singola
     */
    public function pdfNoCompany(Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = TicketReportPdfExport::whereNull('company_id')
            // ->where('is_generated', true)
            ->orderBy('start_date', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    /**
     * Lista approvati per azienda singola
     */
    public function approvedPdfCompany(Company $company, Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1 && ($user['is_company_admin'] != 1 || ! $user->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = TicketReportPdfExport::where([
                'company_id' => $company->id,
                'is_approved_billing' => true,
            ])
            ->orderBy('start_date', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function generic() {}

    /**
     * Nuovo report
     */
    public function storePdfExport(Request $request)
    {

        try {
            $user = $request->user();
            if ($user['is_admin'] != 1) {
                // non è admin
                if ($user['is_company_admin'] != 1) {
                    // non è company admin
                    return response([
                        'message' => 'L\'utente deve essere almeno amministratore aziendale.',
                    ], 401);
                }
                // è company admin
                if (! $user->companies()->where('companies.id', $request->company_id)->exists()) {
                    return response([
                        'message' => 'Puoi richiedere report solo per la tua azienda.',
                    ], 401);
                }
            }

            // Verifica che le date non distino più di 3 mesi
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $today = \Carbon\Carbon::today();
            
            // Verifica che start_date sia prima di end_date
            if ($startDate->isAfter($endDate)) {
                return response([
                    'message' => 'La data di inizio deve essere precedente alla data di fine.',
                ], 422);
            }
            
            // Verifica che end_date non sia nel futuro
            if ($endDate->isAfter($today)) {
                return response([
                    'message' => 'La data di fine non può essere nel futuro.',
                ], 422);
            }
            
            if ($startDate->diffInMonths($endDate) > 3 || 
                ($startDate->diffInMonths($endDate) === 3 && $endDate->day > $startDate->day)) {
                return response([
                    'message' => 'Il periodo selezionato non può superare i 3 mesi.',
                ], 422);
            }

            $company = Company::find($request->company_id);

            // $name = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($company->name)) . '_' . time() . '_' . $request->company_id . '_tickets.pdf';
            
            // $name = time().'_'.$request->company_id.'_tickets.pdf'; 1761744459_Aziendacliente1_2025-10-20_2025-10-29_.pdf
            
            $companyName = preg_replace('/[^a-zA-Z0-9]/', '', $company->name);
            $optionalParamsText = '';
            if (isset($request->optional_parameters)) {
            // Decodifica se è una stringa JSON, altrimenti usa direttamente
            $optionalParameters = is_string($request->optional_parameters) 
                ? json_decode($request->optional_parameters, true) 
                : (array) $request->optional_parameters;
            
                if (isset($optionalParameters['type'])) {
                    $reqOptParType = $optionalParameters['type'];
                    $optionalParamsText .= $reqOptParType == 'all' ? 'Tutti' : ($reqOptParType == 'request' ? 'Request' : ($reqOptParType == 'incident' ? 'Incident' : ''));
                }
            }
            
            $name = time().'_'.$companyName.'_'.$request->start_date.'_'.$request->end_date.'_'.$optionalParamsText.'.pdf';

            // $file =  Excel::store(new TicketsExport($company, $request->start_date, $request->end_date), 'exports/' . $request->company_id . '/' . $name, 'gcs');

            $report = TicketReportPdfExport::create([
                'company_id' => $company->id,
                'file_name' => $name,
                'file_path' => 'pdf_exports/'.$request->company_id.'/'.$name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'optional_parameters' => json_encode($request->optional_parameters),
                'user_id' => $user->id,
            ]);

            dispatch(new GeneratePdfReport($report));

            return response([
                'message' => 'Report creato con successo',
                'report' => $report,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Errore durante la creazione del report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview (restituisce il link generato da google cloud storage)
     */
    public function pdfPreview(TicketReportPdfExport $ticketReportPdfExport, Request $request)
    {

        $user = $request->user();
        if ($user['is_admin'] != 1) {
            // non è admin
            if ($user['is_company_admin'] != 1) {
                // non è company admin
                return response([
                    'message' => 'The user must be at least company admin.',
                ], 401);
            }
            // è company admin

            // Controllo se l'utente appartiene alla company del report
            if ($user->selectedCompany()->id != $ticketReportPdfExport->company_id) {
                return response([
                    'message' => 'You can only preview reports for your selected company.',
                ], 401);
            }
        }

        $url = $this->generatedSignedUrlForFile($ticketReportPdfExport->file_path);

        return response([
            'url' => $url,
            'filename' => $ticketReportPdfExport->file_name,
        ], 200);
    }

    /**
     * Download (restituisce il file)
     */
    public function pdfDownload(TicketReportPdfExport $ticketReportPdfExport, Request $request)
    {

        $user = $request->user();
        // il controllo è così perchè altrimenti stefano che è sia admin che company admin non può scaricare i report
        // perchè se è company admin controllava sempre il company_id, che nel suo caso può essere diverso essendo comunque admin.
        if ($user['is_admin'] != 1) {
            // non è admin
            if ($user['is_company_admin'] != 1) {
                // non è company admin
                return response([
                    'message' => 'The user must be at least company admin.',
                ], 401);
            }
            // è company admin
            // Controllo se l'utente appartiene alla company del report
            if ($user->selectedCompany()->id != $ticketReportPdfExport->company_id) {
                return response([
                    'message' => 'You can only preview reports for your selected company.',
                ], 401);
            }
        }

        $filePath = FileUploadController::storagePathPrefix().$ticketReportPdfExport->file_path;

        $disk = FileUploadController::getStorageDisk();

        if (! Storage::disk($disk)->exists($filePath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $fileContent = Storage::disk($disk)->get($filePath);
        $fileName = $ticketReportPdfExport->file_name;

        /**
         * @disregard Intelephense non rileva il metodo mimeType
         */
        return response($fileContent, 200)
            ->header('Content-Type', Storage::disk($disk)->mimeType($filePath))
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    /**
     * Genera il link temporaneo per il file
     *
     * @param  string  $path
     * @return string
     */
    private function generatedSignedUrlForFile($path)
    {

        return FileUploadController::generateSignedUrlForFile($path);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketReportPdfExport $ticketReportPdfExport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        try {
            $authUser = $request->user();
            if ($authUser['is_admin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            $validatedData = $request->validate([
                'id' => 'required|exists:ticket_report_pdf_exports,id',
                'is_approved_billing' => 'boolean',
                'send_email' => 'boolean',
                // 'approved_billing_identification' => 'nullable|string|unique:ticket_report_pdf_exports,approved_billing_identification',
            ]);

            $ticketReportPdfExport = TicketReportPdfExport::find($request->id);
            if (! $ticketReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }

            // Verifico se il report è stato approvato (quindi collegabile alle fatture tramite il suo identificativo)
            // è stato deciso che dev'essere possibile modificare un report anche se è già stato approvato
            // if ($ticketReportPdfExport->is_approved_billing == 1 && $validatedData['is_approved_billing'] == 0) {
            //     return response([
            //         'message' => 'You can\'t unapprove a report that has been approved for billing.',
            //     ], 401);
            // }

            // Genero l'identificativo da utilizzare per la fatturazione
            if (isset($validatedData['is_approved_billing']) && $validatedData['is_approved_billing'] == 1 && ! $ticketReportPdfExport->approved_billing_identification) {
                $validatedData['approved_billing_identification'] = $ticketReportPdfExport->generatePdfIdentificationString();
            }

            $isApprovedBilling = $validatedData['is_approved_billing'] ?? $ticketReportPdfExport->is_approved_billing;
            $sendEmail = $validatedData['send_email'] ?? false;
            
            // Determina se inviare l'email in base ai cambiamenti
            $shouldSendEmail = false;
            
            // Caso 1: send_email è cambiato da false a true e is_approved_billing è true
            if ($sendEmail && !$ticketReportPdfExport->send_email && $isApprovedBilling) {
                $shouldSendEmail = true;
            }

            // Caso 2: is_approved_billing è cambiato da false a true e send_email è true
            if (isset($validatedData['is_approved_billing']) && 
                $validatedData['is_approved_billing'] == 1 && 
                !$ticketReportPdfExport->is_approved_billing && 
                $sendEmail) {
                $shouldSendEmail = true;
            }

            // Imposta lo stato pending se deve essere inviata
            // Possibili valori: null, 'pending', 'sent', 'failed', 'resend_requested'
            if ($shouldSendEmail) {
                $validatedData['email_status'] = 'pending';
            }

            $ticketReportPdfExport->update($validatedData);

            // Invia email se necessario
            if ($shouldSendEmail) {
                // Verifica che il report sia stato generato
                if (! $ticketReportPdfExport->is_generated) {
                    $ticketReportPdfExport->update([
                        'email_status' => "failed",
                    ]);
                    return response([
                        'message' => 'Cannot send email: report not generated yet or generation failed.',
                        'report' => $ticketReportPdfExport,
                    ], 422);
                }

                // Verifica che il file esista
                $disk = FileUploadController::getStorageDisk();
                if (! Storage::disk($disk)->exists($ticketReportPdfExport->file_path)) {
                    $ticketReportPdfExport->update([
                        'email_status' => "failed",
                    ]);
                    return response([
                        'message' => 'Cannot send email: report file not found in storage.',
                        'report' => $ticketReportPdfExport,
                    ], 404);
                }

                // Dispatch del job per l'invio dell'email
                dispatch(new \App\Jobs\SendPdfReportUpdatedEmail($ticketReportPdfExport));
            }

            return response([
                'message' => 'Report updated successfully',
                'report' => $ticketReportPdfExport,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error updating the report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function resendEmail(TicketReportPdfExport $ticketReportPdfExport)
    {
        try {
            $authUser = request()->user();
            if ($authUser['is_admin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }

            // Verifica che il report sia stato generato
            if (! $ticketReportPdfExport->is_generated) {
                return response([
                    'message' => 'Cannot send email: report not generated yet or generation failed.',
                    'report' => $ticketReportPdfExport,
                ], 422);
            }

            // Verifica che il report sia approvato per la fatturazione e che send_email sia true
            if (! $ticketReportPdfExport->is_approved_billing || ! $ticketReportPdfExport->send_email) {
                return response([
                    'message' => 'Cannot send email: report is not approved for billing or email sending is disabled.',
                    'report' => $ticketReportPdfExport,
                ], 422);
            }

            // Verifica che il file esista
            $disk = FileUploadController::getStorageDisk();
            if (! Storage::disk($disk)->exists($ticketReportPdfExport->file_path)) {
                return response([
                    'message' => 'Cannot send email: report file not found in storage.',
                    'report' => $ticketReportPdfExport,
                ], 404);
            }
            
            // Imposta lo stato a resend_requested
            // Possibili valori: null, 'pending', 'sent', 'failed', 'resend_requested'
            $ticketReportPdfExport->email_status = 'resend_requested';
            $ticketReportPdfExport->save();
            
            // Dispatch del job per l'invio dell'email
            dispatch(new \App\Jobs\SendPdfReportUpdatedEmail($ticketReportPdfExport));

            return response([
                'message' => 'Email resend scheduled successfully',
                'report' => $ticketReportPdfExport,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error resending the email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerates the report
     */
    public function regenerate(Request $request)
    {
        try {
            $authUser = request()->user();
            if ($authUser['is_admin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            // Verifico se il report esiste
            $ticketReportPdfExport = TicketReportPdfExport::find($request->id);
            if (! $ticketReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }
            // Verifico se il report è stato approvato (quindi collegabile alle fatture tramite il suo identificativo)
            if ($ticketReportPdfExport->is_approved_billing == 1) {
                return response([
                    'message' => 'You can\'t regenerate a report that has been approved for billing.',
                ], 401);
            }

            // Cancello il file dal bucket
            if ($ticketReportPdfExport->is_generated) {
                $filePath = $ticketReportPdfExport->file_path;
                $disk = FileUploadController::getStorageDisk();
                if (Storage::disk($disk)->exists($filePath)) {
                    Storage::disk($disk)->delete($filePath);
                }
            }

            // Imposta come non generato e cancella il messaggio di errore
            $ticketReportPdfExport->update([
                'is_generated' => false,
                'error_message' => null,
                'is_failed' => false,
            ]);

            // Dispatch per rigenerarlo
            dispatch(new GeneratePdfReport($ticketReportPdfExport, true));

            return response([
                'message' => 'The report is scheduled to be regenerated',
                'report' => $ticketReportPdfExport,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error scheduling the report for regeneration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketReportPdfExport $ticketReportPdfExport)
    {
        try {
            $authUser = request()->user();
            if ($authUser['is_admin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            // Verifico se il report esiste
            if (! $ticketReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }
            // Verifico se il report è stato approvato (quindi collegabile alle fatture tramite il suo identificativo)
            if ($ticketReportPdfExport->is_approved_billing == 1) {
                return response([
                    'message' => 'You can\'t delete a report that has been approved for billing.',
                ], 401);
            }
            // Cancello il file dal bucket
            if ($ticketReportPdfExport->is_generated) {
                $filePath = $ticketReportPdfExport->file_path;
                $disk = FileUploadController::getStorageDisk();
                if (Storage::disk($disk)->exists($filePath)) {
                    Storage::disk($disk)->delete($filePath);
                }
            }
            // Cancello il report dal db
            $ticketReportPdfExport->delete();

            return response([
                'message' => 'Report deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error deleting the report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updatePdfQuery(Request $request)
    {
        return response ([
            'message' => 'Funzione temporaneamente disabilitata.',
        ], 503);
        try {
            $authUser = $request->user();
            if ($authUser['is_superadmin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            $validatedData = $request->validate([
                'id' => 'required|exists:ticket_report_pdf_exports,id',
                'ai_query' => 'required|string',
            ]);

            $ticketReportPdfExport = TicketReportPdfExport::find($request->id);
            if (! $ticketReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }

            if($ticketReportPdfExport->is_approved_biling) {
                return response([
                    'message' => 'Non puoi modificare la query AI di un report approvato.',
                ], 401);
            }

            if($validatedData['ai_query'] === $ticketReportPdfExport->ai_query) {
                return response([
                    'message' => 'La query AI fornita è identica a quella esistente. Nessuna modifica apportata.',
                ], 400);
            }
            
            (new VertexAiController())->validateSqlQuery($validatedData['ai_query']);

            // Quando si aggiorna la query si deve anche rigenerare il report (quindi aggiungere le operazioni della funzione regenerate)
            // Cancello il file dal bucket
            if ($ticketReportPdfExport->is_generated) {
                $filePath = $ticketReportPdfExport->file_path;
                $disk = FileUploadController::getStorageDisk();
                if (Storage::disk($disk)->exists($filePath)) {
                    Storage::disk($disk)->delete($filePath);
                }
            }

            // Aggiorna la query, imposta come non generato e cancella il messaggio di errore
            $ticketReportPdfExport->update([
                'ai_query' => $validatedData['ai_query'],
                'is_generated' => false,
                'error_message' => null,
                'is_failed' => false,
            ]);

            // Dispatch per rigenerarlo
            dispatch(new GeneratePdfReport($ticketReportPdfExport, true));

            return response([
                'message' => 'Report AI query updated successfully',
                'report' => $ticketReportPdfExport,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error updating the report AI query',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

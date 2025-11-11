<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePdfProjectReport;
use App\Models\Company;
use App\Models\ProjectReportPdfExport;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectReportPdfExportController extends Controller
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

        $reports = ProjectReportPdfExport::where('company_id', $company->id)
            // ->where('is_generated', true)
            ->orderBy('start_date', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function pdfProject(Ticket $project, Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = ProjectReportPdfExport::where('project_id', $project->id)
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
    public function approvedProjectPdfCompany(Company $company, Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1 && ($user['is_company_admin'] != 1 || ! $user->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = ProjectReportPdfExport::where([
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
    public function storeProjectPdfExport(Request $request)
    {

        try {
            $user = $request->user();

            if ($user['is_admin'] != 1) {
                // non è admin
                if ($user['is_company_admin'] != 1) {
                    // non è company admin
                    return response([
                        'message' => 'The user must be at least company admin.',
                    ], 401);
                }
            }

            $project = Ticket::find($request->project_id);
            if (! $project) {
                return response([
                    'message' => 'Project not found.',
                ], 404);
            }

            // è company admin
            if (!$user['is_admin'] && !$user->companies()->where('companies.id', $project->company_id)->exists()) {
                return response([
                    'message' => 'You can only request reports for your company.',
                ], 401);
            }

            $company = $project->company;

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

            $report = ProjectReportPdfExport::create([
                'company_id' => $company->id,
                'project_id' => $request->project_id,
                'file_name' => $name,
                'file_path' => 'pdf_project_exports/'.$project->id.'/'.$name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'optional_parameters' => json_encode($request->optional_parameters),
                'user_id' => $user->id,
                'send_email' => $request->send_email ?? 0,
            ]);

            dispatch(new GeneratePdfProjectReport($report));

            return response([
                'message' => 'Report created successfully',
                'report' => $report,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error generating the report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview (restituisce il link generato da google cloud storage)
     */
    public function projectPdfPreview(ProjectReportPdfExport $projectReportPdfExport, Request $request)
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
            if ($user->selectedCompany()->id != $projectReportPdfExport->company_id) {
                return response([
                    'message' => 'You can only preview reports for your selected company.',
                ], 401);
            }
        }

        // Verifica se il report è stato generato con successo
        if (! $projectReportPdfExport->is_generated) {
            return response([
                'message' => 'Report not generated yet or generation failed.',
                'status' => $projectReportPdfExport->is_failed ? 'failed' : 'pending',
                'error' => $projectReportPdfExport->error_message,
            ], 422);
        }

        // Verifica se il file esiste effettivamente nel storage
        $disk = FileUploadController::getStorageDisk();
        if (! Storage::disk($disk)->exists($projectReportPdfExport->file_path)) {
            return response([
                'message' => 'Report file not found in storage.',
                'file_path' => $projectReportPdfExport->file_path,
            ], 404);
        }

        $url = $this->generatedSignedUrlForFile($projectReportPdfExport->file_path);

        return response([
            'url' => $url,
            'filename' => $projectReportPdfExport->file_name,
        ], 200);
    }

    /**
     * Download (restituisce il file)
     */
    public function projectPdfDownload(ProjectReportPdfExport $projectReportPdfExport, Request $request)
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
            if ($user->selectedCompany()->id != $projectReportPdfExport->company_id) {
                return response([
                    'message' => 'You can only preview reports for your selected company.',
                ], 401);
            }
        }

        // Verifica se il report è stato generato con successo
        if (! $projectReportPdfExport->is_generated) {
            return response()->json([
                'message' => 'Report not generated yet or generation failed.',
                'status' => $projectReportPdfExport->is_failed ? 'failed' : 'pending',
                'error' => $projectReportPdfExport->error_message,
            ], 422);
        }

        $filePath = $projectReportPdfExport->file_path;
        $disk = FileUploadController::getStorageDisk();

        if (! Storage::disk($disk)->exists($filePath)) {
            return response()->json([
                'message' => 'File not found in storage.',
                'file_path' => $filePath,
                'disk' => $disk,
            ], 404);
        }

        $fileContent = Storage::disk($disk)->get($filePath);
        $fileName = $projectReportPdfExport->file_name;

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
    public function show(ProjectReportPdfExport $projectReportPdfExport)
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
                'id' => 'required|exists:project_report_pdf_exports,id',
                'is_approved_billing' => 'boolean',
                // 'approved_billing_identification' => 'nullable|string|unique:project_report_pdf_exports,approved_billing_identification',
            ]);

            $projectReportPdfExport = ProjectReportPdfExport::find($request->id);
            if (! $projectReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }

            // Verifico se il report è stato approvato (quindi collegabile alle fatture tramite il suo identificativo)
            // è stato deciso che dev'essere possibile modificare un report anche se è già stato approvato
            // if ($projectReportPdfExport->is_approved_billing == 1 && $validatedData['is_approved_billing'] == 0) {
            //     return response([
            //         'message' => 'You can\'t unapprove a report that has been approved for billing.',
            //     ], 401);
            // }

            // Genero l'identificativo da utilizzare per la fatturazione
            if ($validatedData['is_approved_billing'] == 1 && ! $projectReportPdfExport->approved_billing_identification) {
                $validatedData['approved_billing_identification'] = $projectReportPdfExport->generatePdfIdentificationString();
            }

            $projectReportPdfExport->update($validatedData);

            return response([
                'message' => 'Report updated successfully',
                'report' => $projectReportPdfExport,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error updating the report',
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
            $projectReportPdfExport = ProjectReportPdfExport::find($request->id);
            if (! $projectReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }
            // Verifico se il report è stato approvato (quindi collegabile alle fatture tramite il suo identificativo)
            if ($projectReportPdfExport->is_approved_billing == 1) {
                return response([
                    'message' => 'You can\'t regenerate a report that has been approved for billing.',
                ], 401);
            }

            // Cancello il file dal bucket
            if ($projectReportPdfExport->is_generated) {
                $filePath = $projectReportPdfExport->file_path;
                $disk = FileUploadController::getStorageDisk();
                if (Storage::disk($disk)->exists($filePath)) {
                    Storage::disk($disk)->delete($filePath);
                }
            }

            // Imposta come non generato e cancella il messaggio di errore
            $projectReportPdfExport->update([
                'is_generated' => false,
                'error_message' => null,
                'is_failed' => false,
            ]);

            // Dispatch per rigenerarlo
            dispatch(new GeneratePdfProjectReport($projectReportPdfExport));

            return response([
                'message' => 'The report is scheduled to be regenerated',
                'report' => $projectReportPdfExport,
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
    public function destroy(ProjectReportPdfExport $projectReportPdfExport)
    {
        try {
            $authUser = request()->user();
            if ($authUser['is_admin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            // Verifico se il report esiste
            if (! $projectReportPdfExport) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }
            // Verifico se il report è stato approvato (quindi collegabile alle fatture tramite il suo identificativo)
            if ($projectReportPdfExport->is_approved_billing == 1) {
                return response([
                    'message' => 'You can\'t delete a report that has been approved for billing.',
                ], 401);
            }
            // Cancello il file dal bucket
            if ($projectReportPdfExport->is_generated) {
                $filePath = $projectReportPdfExport->file_path;
                $disk = FileUploadController::getStorageDisk();
                if (Storage::disk($disk)->exists($filePath)) {
                    Storage::disk($disk)->delete($filePath);
                }
            }
            // Cancello il report dal db
            $projectReportPdfExport->delete();

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
}

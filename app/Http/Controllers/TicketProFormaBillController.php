<?php

namespace App\Http\Controllers;

use App\Models\TicketProFormaBill;
use App\Models\Company;
use App\Jobs\GenerateTicketProFormaBillJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketProFormaBillController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->is_superadmin ?? false) {
            $bills = TicketProFormaBill::with([
                    'company' => function ($query) {
                        $query->select('id', 'name', 'logo_url');
                    },
                    'user' => function ($query) {
                        $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_superadmin', 'is_company_admin', 'is_deleted');
                    },
                ])
                ->orderBy('start_date', 'DESC')
                ->get();
        } else if ($user->is_company_admin ?? false) {
            $selectedCompany = $user->selectedCompany() ?? null;
            if(! $selectedCompany) {
                return response()->json(['message' => 'Unauthorized. No company selected'], 401);
            }
            $bills = TicketProFormaBill::with([
                    'company' => function ($query) {
                        $query->select('id', 'name', 'logo_url');
                    },
                ])
                ->where('is_approved', true)
                ->where('company_id', $selectedCompany->id)
                ->orderBy('start_date', 'DESC')
                ->get();
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return response()->json([
            'pro_forma_bills' => $bills
        ]);
    }
    
    public function companyProFormaBills(Company $company, Request $request) {
        $user = $request->user();
        if ($user->is_superadmin ?? false) {
            $bills = TicketProFormaBill::with([
                    'company' => function ($query) {
                        $query->select('id', 'name', 'logo_url');
                    },
                    'user' => function ($query) {
                        $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_superadmin', 'is_company_admin', 'is_deleted');
                    },
                ])
                ->where('company_id', $company->id)
                ->orderBy('start_date', 'DESC')
                ->get();
        } else if ($user->is_company_admin ?? false) {
            $selectedCompany = $user->selectedCompany() ?? null;
            if ($selectedCompany->id !== $company->id) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $bills = TicketProFormaBill::with([
                    'company' => function ($query) {
                        $query->select('id', 'name', 'logo_url');
                    },
                ])
                ->where('is_approved', true)
                ->where('company_id', $selectedCompany->id)
                ->orderBy('start_date', 'DESC')
                ->get();
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return response()->json([
            'pro_forma_bills' => $bills
        ]);
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user->is_superadmin) {
                return response([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'optional_parameters' => 'nullable|array',
            ]);

            $fileName = time().'_'.$request->company_id .'_pro_forma.pdf';
            $filePath = 'pro_forma/'. $request->company_id . '/' . $fileName;

            $proFormaBill = TicketProFormaBill::create([
                'company_id' => $request->company_id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'user_id' => $user->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'optional_parameters' => $request->optional_parameters,
            ]);
            // Qui puoi dispatchare il job di generazione PDF se serve
            // GenerateTicketProFormaBillJob::dispatch($bill);
            dispatch(new GenerateTicketProFormaBillJob($proFormaBill));

            return response([
                'message' => 'Pro forma created successfully',
                'pro_forma_bill' => $proFormaBill,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error generating the pro forma',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        //
    }

    public function pdfPreview(TicketProFormaBill $proFormaBill, Request $request)
    {

        $user = $request->user();
        if ($user['is_superadmin'] != 1) {
            // non è superadmin
            if ($user['is_company_admin'] != 1) {
                // non è company admin
                return response([
                    'message' => 'The user must be at least company admin.',
                ], 401);
            }
            // è company admin

            // Controllo se l'utente appartiene alla company del report
            if ($user->selectedCompany()->id != $proFormaBill->company_id) {
                return response([
                    'message' => 'You can only preview reports for your selected company.',
                ], 401);
            }
        }

        $url = $this->generatedSignedUrlForFile($proFormaBill->file_path);

        return response([
            'url' => $url,
            'filename' => $proFormaBill->file_name,
        ], 200);
    }

    public function pdfDownload(TicketProFormaBill $proFormaBill, Request $request)
    {

        $user = $request->user();

        if($user->cannot('download', $proFormaBill)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $filePath = $proFormaBill->file_path;

        $disk = FileUploadController::getStorageDisk();

        if (! Storage::disk($disk)->exists($filePath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $fileContent = Storage::disk($disk)->get($filePath);
        $fileName = $proFormaBill->file_name;

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

    public function update(Request $request)
    {
        try {
            $authUser = $request->user();
            if ($authUser['is_superadmin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            $validatedData = $request->validate([
                'id' => 'required|exists:ticket_pro_forma_bills,id',
                'is_approved' => 'required|boolean',
            ]);

            $proFormaBill = TicketProFormaBill::find($request->id);
            if (! $proFormaBill) {
                return response([
                    'message' => 'Report not found',
                ], 404);
            }

            // è stato deciso che dev'essere possibile modificare un pro forma anche se è già stato approvato

            $proFormaBill->update($validatedData);

            return response([
                'message' => 'Report updated successfully',
                'report' => $proFormaBill,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error updating the report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function regenerate(Request $request)
    {
        try {
            $authUser = request()->user();
            if ($authUser['is_superadmin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }
            // Verifico se il pro forma esiste
            $request->validate([
                'id' => 'required|exists:ticket_pro_forma_bills,id',
            ]);
            $proFormaBill = TicketProFormaBill::find($request->id);
            if (! $proFormaBill) {
                return response([
                    'message' => 'Pro forma bill not found',
                ], 404);
            }

            // Verifico se il pro forma è approvato
            if ($proFormaBill->is_approved) {
                return response([
                    'message' => 'Can\'t regenerate an approved pro forma bill.',
                ], 400);
            }

            // Cancello il file esistente se c'è
            if($proFormaBill->is_generated) {
                $disk = FileUploadController::getStorageDisk();
                if (Storage::disk($disk)->exists($proFormaBill->file_path)) {
                    Storage::disk($disk)->delete($proFormaBill->file_path);
                }
            }

            // Reimposta lo stato del pro forma bill prima di rigenerarlo
            $proFormaBill->update([
                'is_generated' => false,
                'is_failed' => false,
                'error_message' => null,
            ]);

            // Dispatcha il job per rigenerare il PDF
            dispatch(new GenerateTicketProFormaBillJob($proFormaBill));

            return response([
                'message' => 'Pro forma bill regeneration started successfully',
                'pro_forma_bill' => $proFormaBill,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error regenerating the pro forma bill',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(TicketProFormaBill $proFormaBill)
    {
        try {
            $authUser = request()->user();
            if ($authUser['is_superadmin'] != 1) {
                return response([
                    'message' => 'Unauthorized.',
                ], 401);
            }

            if (! $proFormaBill) {
                return response([
                    'message' => 'Pro forma bill not found',
                ], 404);
            }

            // Elimina il file associato se esiste
            $disk = FileUploadController::getStorageDisk();
            if (Storage::disk($disk)->exists($proFormaBill->file_path)) {
                Storage::disk($disk)->delete($proFormaBill->file_path);
            }

            // Elimina il record dal database
            $proFormaBill->delete();

            return response([
                'message' => 'Pro forma bill deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error deleting the pro forma bill',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

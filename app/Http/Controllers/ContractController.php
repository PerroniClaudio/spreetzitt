<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view contracts',
            ], 403);
        }

        $contracts = Contract::with(['status'])->get();

        return response([
            'contracts' => $contracts,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to create contracts',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status_id' => 'nullable|exists:contract_stages,id',
        ]);

        $contract = Contract::create($data);

        return response([
            'contract' => $contract->load('status'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this contract',
            ], 403);
        }

        $contract->load(['status', 'attachments']);

        return response([
            'contract' => $contract,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contract $contract)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update contracts',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status_id' => 'nullable|exists:contract_stages,id',
        ]);

        $contract->update($data);

        return response([
            'contract' => $contract->load('status'),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to delete contracts',
            ], 403);
        }

        $contract->delete();

        return response([
            'message' => 'Contract deleted successfully',
        ], 200);
    }

    /**
     * Restore a soft deleted contract.
     */
    public function restore($contractId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to restore contracts',
            ], 403);
        }

        $contract = Contract::withTrashed()->findOrFail($contractId);
        $contract->restore();

        return response([
            'message' => 'Contract restored successfully',
            'contract' => $contract->load('status'),
        ], 200);
    }

    /**
     * Force delete a soft deleted contract.
     */
    public function forceDestroy($contractId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to permanently delete contracts',
            ], 403);
        }

        $contract = Contract::withTrashed()->findOrFail($contractId);
        $contract->forceDelete();

        return response([
            'message' => 'Contract permanently deleted',
        ], 200);
    }

    /**
     * Get all contracts including soft deleted ones.
     */
    public function all(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view contracts',
            ], 403);
        }

        $contracts = Contract::withTrashed()->with(['status'])->get();

        return response([
            'contracts' => $contracts,
        ], 200);
    }

    /**
     * Get all attachments for contract
     */
    public function getAttachments(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        $query = $contract->attachments();

        // Admin vede anche gli allegati soft deleted
        if ($authUser->is_admin) {
            $query->withTrashed();
        }

        $attachments = $query->with('uploader:id,name,email,surname,is_superadmin,is_admin,is_company_admin')->get();

        // Aggiungi i permessi per ogni allegato
        $attachments->each(function ($attachment) use ($authUser) {
            $attachment->can_modify = $attachment->canModifyAccessLevel($authUser);
            $attachment->can_delete = $attachment->canDelete($authUser);
        });

        return response(['attachments' => $attachments], 200);
    }

    /**
     * Upload attachment for contract
     */
    public function uploadAttachment(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        $fields = $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'display_name' => 'nullable|string|max:255',
            'access_level' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $defaultAccessLevel = $fields['access_level'] ?? $authUser->getUserLevel() ?? config('permissions.default_access_level');

        // Genera nome file univoco
        $extension = $file->getClientOriginalExtension();
        $uniqueName = time().'_'.Str::random(10).'.'.$extension;
        $path = 'contracts/'.$contract->id;

        // Upload usando FileUploadController
        $filePath = FileUploadController::storeFile($file, $path, $uniqueName);

        // Crea record nel database
        $attachment = ContractAttachment::create([
            'contract_id' => $contract->id,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'display_name' => $fields['display_name'] ?? null,
            'file_extension' => $extension,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $authUser->id,
            'access_level' => $defaultAccessLevel,
        ]);

        return response([
            'attachment' => $attachment->load('uploader:id,name,email,surname,is_superadmin,is_admin,is_company_admin'),
            'message' => 'File caricato con successo',
        ], 201);
    }

    /**
     * Upload multiple attachments for contract
     */
    public function uploadAttachments(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'access_level' => 'nullable|string',
        ]);

        if (! $request->hasFile('files')) {
            return response(['message' => 'No files uploaded'], 400);
        }

        $files = $request->file('files');
        $uploadedAttachments = [];
        $count = 0;
        $defaultAccessLevel = $validated['access_level'] ?? $authUser->getUserLevel() ?? config('permissions.default_access_level');

        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file->isValid()) {
                    // Genera nome file univoco
                    $extension = $file->getClientOriginalExtension();
                    $uniqueName = time().'_'.Str::random(10).'.'.$extension;
                    $basePath = 'contracts/'.$contract->id;

                    // Upload usando FileUploadController
                    $filePath = FileUploadController::storeFile($file, $basePath, $uniqueName);

                    // Crea record nel database
                    $originalFilename = $file->getClientOriginalName();
                    $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);

                    $attachment = ContractAttachment::create([
                        'contract_id' => $contract->id,
                        'file_path' => $filePath,
                        'original_filename' => $originalFilename,
                        'display_name' => $displayName,
                        'file_extension' => $extension,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $authUser->id,
                        'access_level' => $defaultAccessLevel,
                    ]);

                    $uploadedAttachments[] = $attachment;
                    $count++;
                }
            }
        } else {
            // Singolo file
            if ($files->isValid()) {
                $extension = $files->getClientOriginalExtension();
                $uniqueName = time().'_'.Str::random(10).'.'.$extension;
                $basePath = 'contracts/'.$contract->id;

                $filePath = FileUploadController::storeFile($files, $basePath, $uniqueName);

                $originalFilename = $files->getClientOriginalName();
                $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);

                $attachment = ContractAttachment::create([
                    'contract_id' => $contract->id,
                    'file_path' => $filePath,
                    'original_filename' => $originalFilename,
                    'display_name' => $displayName,
                    'file_extension' => $extension,
                    'mime_type' => $files->getMimeType(),
                    'file_size' => $files->getSize(),
                    'uploaded_by' => $authUser->id,
                    'access_level' => $defaultAccessLevel,
                ]);

                $uploadedAttachments[] = $attachment;
                $count++;
            }
        }

        return response([
            'attachments' => $uploadedAttachments,
            'filesCount' => $count,
            'message' => $count.' file caricati con successo',
        ], 201);
    }

    /**
     * Update attachment display name
     */
    public function updateAttachment(Contract $contract, ContractAttachment $attachment, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Verifica che l'allegato appartenga al contratto
        if ($attachment->contract_id !== $contract->id) {
            return response(['message' => 'Attachment does not belong to this contract'], 400);
        }

        $fields = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'access_level' => 'nullable|string',
        ]);

        $attachment->update(array_filter($fields, fn ($v) => $v !== null));

        return response([
            'attachment' => $attachment,
            'message' => 'Nome allegato aggiornato',
        ], 200);
    }

    /**
     * Delete attachment (soft delete)
     */
    public function deleteAttachment(Contract $contract, ContractAttachment $attachment, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Verifica che l'allegato appartenga al contratto
        if ($attachment->contract_id !== $contract->id) {
            return response(['message' => 'Attachment does not belong to this contract'], 400);
        }

        // Soft delete (il file rimane su GCS)
        $attachment->delete();

        return response(['message' => 'Allegato eliminato'], 200);
    }

    /**
     * Restore soft deleted attachment (solo admin)
     */
    public function restoreAttachment(Contract $contract, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Trova l'allegato soft deleted
        $attachment = ContractAttachment::withTrashed()
            ->where('id', $attachmentId)
            ->where('contract_id', $contract->id)
            ->first();

        if (! $attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        if (! $attachment->trashed()) {
            return response(['message' => 'Attachment is not deleted'], 400);
        }

        $attachment->restore();

        return response([
            'attachment' => $attachment->load('uploader:id,name,email,surname,is_superadmin,is_admin,is_company_admin'),
            'message' => 'Allegato ripristinato',
        ], 200);
    }

    /**
     * Force delete attachment (solo admin - elimina definitivamente file da GCS)
     */
    public function forceDeleteAttachment(Contract $contract, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Trova l'allegato soft deleted
        $attachment = ContractAttachment::withTrashed()
            ->where('id', $attachmentId)
            ->where('contract_id', $contract->id)
            ->first();

        if (! $attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Force delete (elimina file da GCS tramite boot del model)
        $attachment->forceDelete();

        return response(['message' => 'Allegato eliminato definitivamente'], 200);
    }

    /**
     * Get download URL for attachment
     */
    public function getDownloadUrl(Contract $contract, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Admin può scaricare anche file soft deleted
        $attachment = $authUser->is_admin
            ? ContractAttachment::withTrashed()->find($attachmentId)
            : ContractAttachment::find($attachmentId);

        if (! $attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Verifica che l'allegato appartenga al contratto
        if ($attachment->contract_id !== $contract->id) {
            return response(['message' => 'Attachment does not belong to this contract'], 400);
        }

        $url = $attachment->getDownloadUrl();

        return response([
            'url' => $url,
            'filename' => $attachment->downloadFilename(),
        ], 200);
    }

    /**
     * Get preview URL for attachment
     */
    public function getPreviewUrl(Contract $contract, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Admin può vedere preview anche di file soft deleted
        $attachment = $authUser->is_admin
            ? ContractAttachment::withTrashed()->find($attachmentId)
            : ContractAttachment::find($attachmentId);

        if (! $attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Verifica che l'allegato appartenga al contratto
        if ($attachment->contract_id !== $contract->id) {
            return response(['message' => 'Attachment does not belong to this contract'], 400);
        }

        $url = $attachment->getPreviewUrl();

        return response([
            'url' => $url,
            'filename' => $attachment->downloadFilename(),
            'is_image' => $attachment->isImage(),
            'is_pdf' => $attachment->isPdf(),
        ], 200);
    }

    /**
     * Get all invoices associated with the contract.
     */
    public function getInvoices(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view contract invoices',
            ], 403);
        }

        $invoices = $contract->invoices()
            ->withPivot('reference_period_start', 'reference_period_end', 'created_at', 'updated_at')
            ->get();

        return response([
            'invoices' => $invoices,
        ], 200);
    }

    /**
     * Attach one or more invoices to the contract.
     */
    public function attachInvoices(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to attach invoices to contracts',
            ], 403);
        }

        $data = $request->validate([
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'required|exists:invoices,id',
            'reference_period_start' => 'nullable|date',
            'reference_period_end' => 'nullable|date|after_or_equal:reference_period_start',
        ]);

        $pivotData = [];
        if (isset($data['reference_period_start']) || isset($data['reference_period_end'])) {
            $pivotData = array_filter([
                'reference_period_start' => $data['reference_period_start'] ?? null,
                'reference_period_end' => $data['reference_period_end'] ?? null,
            ]);
        }

        // Attach invoices with pivot data
        foreach ($data['invoice_ids'] as $invoiceId) {
            $contract->invoices()->syncWithoutDetaching([
                $invoiceId => $pivotData,
            ]);
        }

        return response([
            'message' => 'Invoices attached successfully',
            'invoices' => $contract->invoices()
                ->withPivot('reference_period_start', 'reference_period_end', 'created_at', 'updated_at')
                ->get(),
        ], 200);
    }

    /**
     * Detach an invoice from the contract.
     */
    public function detachInvoice(Contract $contract, Invoice $invoice, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to detach invoices from contracts',
            ], 403);
        }

        $contract->invoices()->detach($invoice->id);

        return response([
            'message' => 'Invoice detached successfully',
        ], 200);
    }

    /**
     * Sync invoices for the contract (replace all existing associations).
     */
    public function syncInvoices(Contract $contract, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to sync contract invoices',
            ], 403);
        }

        $data = $request->validate([
            'invoices' => 'required|array',
            'invoices.*.id' => 'required|exists:invoices,id',
            'invoices.*.reference_period_start' => 'nullable|date',
            'invoices.*.reference_period_end' => 'nullable|date|after_or_equal:invoices.*.reference_period_start',
        ]);

        $syncData = [];
        foreach ($data['invoices'] as $invoiceData) {
            $syncData[$invoiceData['id']] = array_filter([
                'reference_period_start' => $invoiceData['reference_period_start'] ?? null,
                'reference_period_end' => $invoiceData['reference_period_end'] ?? null,
            ]);
        }

        $contract->invoices()->sync($syncData);

        return response([
            'message' => 'Invoices synced successfully',
            'invoices' => $contract->invoices()
                ->withPivot('reference_period_start', 'reference_period_end', 'created_at', 'updated_at')
                ->get(),
        ], 200);
    }

    /**
     * Update pivot data for a specific invoice-contract association.
     */
    public function updateInvoicePivot(Contract $contract, Invoice $invoice, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update invoice-contract associations',
            ], 403);
        }

        // Check if the invoice is attached to the contract
        if (! $contract->invoices()->where('invoices.id', $invoice->id)->exists()) {
            return response([
                'message' => 'This invoice is not attached to the contract',
            ], 404);
        }

        $data = $request->validate([
            'reference_period_start' => 'nullable|date',
            'reference_period_end' => 'nullable|date|after_or_equal:reference_period_start',
        ]);

        $contract->invoices()->updateExistingPivot($invoice->id, array_filter($data));

        return response([
            'message' => 'Invoice period updated successfully',
            'invoice' => $contract->invoices()
                ->where('invoices.id', $invoice->id)
                ->withPivot('reference_period_start', 'reference_period_end', 'created_at', 'updated_at')
                ->first(),
        ], 200);
    }
}

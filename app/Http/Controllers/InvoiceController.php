<?php

namespace App\Http\Controllers;

use App\Exports\InvoiceTemplateExport;
use App\Exports\TicketInvoiceAssociationTemplateExport;
use App\Imports\InvoiceImport;
use App\Imports\TicketInvoiceAssociationsImport;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin = $authUser['is_superadmin'] == 1;

        if (! $isAdmin) {
            return response()->json([
                'message' => 'Only admins can view invoices.',
            ], 403);
        }

        $query = Invoice::with(['company:id,name', 'paymentStage:id,name,admin_color']);

        if ($request->has('company_id')) {
            if ($request->company_id === 'null' || $request->company_id === null) {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $request->company_id);
            }
        }

        if ($request->has('payment_stage_id')) {
            $query->where('payment_stage_id', $request->payment_stage_id);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->get();

        return response()->json([
            'invoices' => $invoices,
            'message' => 'Invoices retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can create invoices.',
            ], 403);
        }

        $validated = $request->validate([
            'number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'payment_stage_id' => 'nullable|exists:invoice_payment_stages,id',
            'invoice_date' => 'required|date',
        ]);

        $invoice = Invoice::create($validated);
        $invoice->load(['company:id,name', 'paymentStage:id,name,admin_color']);

        return response()->json([
            'invoice' => $invoice,
            'message' => 'Invoice created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin = $authUser['is_admin'] == 1;

        if (! $isAdmin) {
            return response()->json([
                'message' => 'Only admins can view invoices.',
            ], 403);
        }

        $invoice->load(['company', 'paymentStage']);

        return response()->json([
            'invoice' => $invoice,
            'message' => 'Invoice retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can update invoices.',
            ], 403);
        }

        $validated = $request->validate([
            'number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'payment_stage_id' => 'nullable|exists:invoice_payment_stages,id',
            'invoice_date' => 'required|date',
        ]);

        $invoice->update($validated);
        $invoice->load(['company:id,name', 'paymentStage:id,name,admin_color']);

        return response()->json([
            'invoice' => $invoice->fresh(['company', 'paymentStage']),
            'message' => 'Invoice updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can delete invoices.',
            ], 403);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice soft deleted successfully',
        ]);
    }

    /**
     * Restore a soft deleted invoice.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can restore invoices.',
            ], 403);
        }

        $invoice = Invoice::withTrashed()->findOrFail($id);
        $invoice->restore();

        return response()->json([
            'invoice' => $invoice->fresh(['company', 'paymentStage']),
            'message' => 'Invoice restored successfully',
        ]);
    }

    /**
     * Get all invoices including soft deleted ones.
     */
    public function all(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin = $authUser['is_superadmin'] == 1;

        if (! $isAdmin) {
            return response()->json([
                'message' => 'Only admins can view all invoices including deleted ones.',
            ], 403);
        }

        $query = Invoice::withTrashed()->with(['company:id,name', 'paymentStage:id,name,admin_color']);

        if ($request->has('company_id')) {
            $companyId = $request->company_id;
            if ($companyId === 'null' || $companyId === null) {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $companyId);
            }
        }

        if ($request->has('payment_stage_id')) {
            $query->where('payment_stage_id', $request->payment_stage_id);
        }

        // Ordina sempre per invoice_date desc
        $query->orderBy('invoice_date', 'desc');
        $invoices = $query->get();

        return response()->json([
            'invoices' => $invoices,
            'message' => 'All invoices retrieved successfully',
        ]);
    }

    public function exportTemplate(Request $request)
    {
        if ($request->user()['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can download the invoice import template.',
            ], 403);
        }

        return Excel::download(new InvoiceTemplateExport, 'template_import_fatture.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        if ($request->user()['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can import invoices.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        try {
            Excel::import(new InvoiceImport, $request->file('file'));
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Importazione annullata: nessuna fattura è stata salvata.',
                'errors' => array_filter(explode("\n", $exception->getMessage())),
            ], 422);
        }

        return response()->json([
            'message' => 'Importazione completata con successo.',
        ]);
    }

    public function exportTicketAssociationsTemplate(Request $request)
    {
        if ($request->user()['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can download the ticket-invoice association import template.',
            ], 403);
        }

        return Excel::download(new TicketInvoiceAssociationTemplateExport, 'template_associazioni_ticket_fatture.xlsx');
    }

    public function importTicketAssociations(Request $request): JsonResponse
    {
        if ($request->user()['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can import ticket-invoice associations.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        try {
            Excel::import(new TicketInvoiceAssociationsImport, $request->file('file'));
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Importazione annullata: nessuna associazione ticket-fattura è stata salvata.',
                'errors' => array_filter(explode("\n", $exception->getMessage())),
            ], 422);
        }

        return response()->json([
            'message' => 'Associazioni ticket-fattura importate con successo.',
        ]);
    }
}

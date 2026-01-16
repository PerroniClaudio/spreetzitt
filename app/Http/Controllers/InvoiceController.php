<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin = $authUser['is_admin'] == 1;

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
            'number' => 'required|string|max:255|unique:invoices,number',
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
            'number' => 'required|string|max:255|unique:invoices,number,'.$invoice->id,
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
        $isAdmin = $authUser['is_admin'] == 1;

        if (! $isAdmin) {
            return response()->json([
                'message' => 'Only admins can view all invoices including deleted ones.',
            ], 403);
        }

        $query = Invoice::withTrashed()->with(['company:id,name', 'paymentStage:id,name,admin_color']);

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('payment_stage_id')) {
            $query->where('payment_stage_id', $request->payment_stage_id);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->get();

        return response()->json([
            'invoices' => $invoices,
            'message' => 'All invoices retrieved successfully',
        ]);
    }
}

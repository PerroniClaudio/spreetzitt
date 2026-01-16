<?php

namespace App\Http\Controllers;

use App\Models\InvoicePaymentStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicePaymentStageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $stages = InvoicePaymentStage::orderBy('name', 'asc')->get();

        return response()->json([
            'payment_stages' => $stages,
            'message' => 'Invoice payment stages retrieved successfully',
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
                'message' => 'Only superadmins can create invoice payment stages.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:invoice_payment_stages,name',
            'description' => 'nullable|string',
            'admin_color' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
        ]);

        $stage = InvoicePaymentStage::create($validated);

        return response()->json([
            'payment_stage' => $stage,
            'message' => 'Invoice payment stage created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(InvoicePaymentStage $invoicePaymentStage): JsonResponse
    {
        return response()->json([
            'payment_stage' => $invoicePaymentStage,
            'message' => 'Invoice payment stage retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InvoicePaymentStage $invoicePaymentStage): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can update invoice payment stages.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:invoice_payment_stages,name,'.$invoicePaymentStage->id,
            'description' => 'nullable|string',
            'admin_color' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
        ]);

        $invoicePaymentStage->update($validated);

        return response()->json([
            'payment_stage' => $invoicePaymentStage->fresh(),
            'message' => 'Invoice payment stage updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, InvoicePaymentStage $invoicePaymentStage): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser['is_superadmin'] != 1) {
            return response()->json([
                'message' => 'Only superadmins can delete invoice payment stages.',
            ], 403);
        }

        $isUsed = $invoicePaymentStage->invoices()->exists();

        if ($isUsed) {
            $invoicePaymentStage->delete();
            $message = 'Invoice payment stage soft deleted successfully (it is used in invoices)';
        } else {
            $invoicePaymentStage->forceDelete();
            $message = 'Invoice payment stage permanently deleted successfully';
        }

        return response()->json([
            'message' => $message,
        ]);
    }

    /**
     * Get all payment stages including soft deleted ones.
     */
    public function all(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser['is_admin'] != 1) {
            return response()->json([
                'message' => 'Only admins can view all invoice payment stages.',
            ], 403);
        }

        $stages = InvoicePaymentStage::withTrashed()->orderBy('name', 'asc')->get();

        return response()->json([
            'payment_stages' => $stages,
            'message' => 'All invoice payment stages retrieved successfully',
        ]);
    }
}

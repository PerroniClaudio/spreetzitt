<?php

namespace App\Http\Controllers;

use App\Models\TicketStage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TicketStageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $stages = TicketStage::active()->ordered()->get();
        
        return response()->json([
            'stages' => $stages,
            'message' => 'Ticket stages retrieved successfully'
        ]);
    }

    /**
     * Get stages formatted for select options
     */
    public function options(): JsonResponse
    {
        $stages = TicketStage::active()
            ->ordered()
            ->get(['id', 'name', 'admin_color', 'user_color'])
            ->map(function ($stage) {
                return [
                    'value' => $stage->id,
                    'label' => $stage->name,
                    'admin_color' => $stage->admin_color,
                    'user_color' => $stage->user_color
                ];
            });

        return response()->json([
            'options' => $stages,
            'message' => 'Stage options retrieved successfully'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ticket_stages,name',
            'description' => 'nullable|string',
            'admin_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'user_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'order' => 'nullable|integer|min:0',
            'is_sla_pause' => 'boolean'
        ]);

        if (!isset($validated['order'])) {
            $validated['order'] = TicketStage::max('order') + 1;
        }

        $stage = TicketStage::create($validated);

        return response()->json([
            'stage' => $stage,
            'message' => 'Ticket stage created successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketStage $ticketStage): JsonResponse
    {
        return response()->json([
            'stage' => $ticketStage,
            'message' => 'Ticket stage retrieved successfully'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketStage $ticketStage): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ticket_stages', 'name')->ignore($ticketStage->id)
            ],
            'description' => 'nullable|string',
            'admin_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'user_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'order' => 'nullable|integer|min:0',
            'is_sla_pause' => 'boolean'
        ]);

        $ticketStage->update($validated);

        return response()->json([
            'stage' => $ticketStage->fresh(),
            'message' => 'Ticket stage updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(TicketStage $ticketStage): JsonResponse
    {
        // Il controllo su is_system lo fa direttamente il modello.
        // Se ci sono ticket con quello stage non si può eliminare, anche per evitare casini nella visualizzazione della lista ticket 
        // (che ha le checkbox coi filtri per stato, quindi se lo stato non è tra le checkbox non si vedrebbero i ticket ecc ecc.)
        if ($ticketStage->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot disable this stage because there are tickets assigned to it.'
            ], 422);
        }

        $ticketStage->delete();

        return response()->json([
            'message' => 'Ticket stage disabled successfully'
        ]);
    }

    /**
     * Restore a soft deleted ticket stage.
     */
    public function restore(int $id): JsonResponse
    {
        $stage = TicketStage::withTrashed()->findOrFail($id);
        $stage->restore();

        return response()->json([
            'stage' => $stage->fresh(),
            'message' => 'Ticket stage enabled successfully'
        ]);
    }

    /**
     * Get all stages including disabled ones.
     */
    public function all(): JsonResponse
    {
        $stages = TicketStage::withTrashed()->ordered()->get();
        
        return response()->json([
            'stages' => $stages,
            'message' => 'All ticket stages retrieved successfully'
        ]);
    }
}

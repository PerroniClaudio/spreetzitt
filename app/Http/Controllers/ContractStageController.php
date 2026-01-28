<?php

namespace App\Http\Controllers;

use App\Models\ContractStage;
use Illuminate\Http\Request;

class ContractStageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view contract stages',
            ], 403);
        }

        $stages = ContractStage::all();

        return response([
            'stages' => $stages,
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
                'message' => 'You are not allowed to create contract stages',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'admin_color' => 'nullable|string|max:20',
        ]);

        $stage = ContractStage::create($data);

        return response([
            'stage' => $stage,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ContractStage $contractStage)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this contract stage',
            ], 403);
        }

        return response([
            'stage' => $contractStage,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ContractStage $contractStage)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update contract stages',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'admin_color' => 'nullable|string|max:20',
        ]);

        $contractStage->update($data);

        return response([
            'stage' => $contractStage,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ContractStage $contractStage)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to delete contract stages',
            ], 403);
        }

        $contractStage->delete();

        return response([
            'message' => 'Contract stage deleted successfully',
        ], 200);
    }

    /**
     * Restore a soft deleted stage.
     */
    public function restore($stageId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to restore contract stages',
            ], 403);
        }

        $stage = ContractStage::withTrashed()->findOrFail($stageId);
        $stage->restore();

        return response([
            'message' => 'Contract stage restored successfully',
            'stage' => $stage,
        ], 200);
    }

    /**
     * Force delete a soft deleted stage.
     */
    public function forceDestroy($stageId, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to permanently delete contract stages',
            ], 403);
        }

        $stage = ContractStage::withTrashed()->findOrFail($stageId);
        $stage->forceDelete();

        return response([
            'message' => 'Contract stage permanently deleted',
        ], 200);
    }

    /**
     * Get all contract stages including soft deleted ones.
     */
    public function all(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view contract stages',
            ], 403);
        }

        $stages = ContractStage::withTrashed()->get();

        return response([
            'stages' => $stages,
        ], 200);
    }
}

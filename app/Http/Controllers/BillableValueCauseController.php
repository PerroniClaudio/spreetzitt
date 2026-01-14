<?php

namespace App\Http\Controllers;

use App\Models\BillableValueCause;
use Illuminate\Http\Request;

class BillableValueCauseController extends Controller
{
    public function index(Request $request)
    {
        $query = BillableValueCause::query()->orderBy('name');

        return response()->json(['causes' => $query->get()]);
    }

    public function all(Request $request)
    {
        $query = BillableValueCause::withTrashed()->orderBy('name');

        return response()->json(['causes' => $query->get()]);
    }

    public function store(Request $request)
    {
        $authUser = $request->user();
        if (! $authUser->is_superadmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $billableValueCause = BillableValueCause::create($validated);

        return response()->json($billableValueCause, 201);
    }

    public function show(BillableValueCause $billableValueCause)
    {
        return response()->json($billableValueCause);
    }

    public function update(Request $request, BillableValueCause $billableValueCause)
    {
        $authUser = $request->user();
        if (! $authUser->is_superadmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $billableValueCause->update($validated);

        return response()->json($billableValueCause);
    }

    public function destroy(Request $request, BillableValueCause $billableValueCause)
    {
        $authUser = $request->user();
        if (! $authUser->is_superadmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $billableValueCause->delete();

        return response()->json(['message' => 'Billable value cause soft deleted'], 200);
    }

    public function forceDestroy(Request $request, $id)
    {
        $authUser = $request->user();
        if (! $authUser->is_superadmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $billableValueCause = BillableValueCause::withTrashed()->findOrFail($id);
        $billableValueCause->forceDelete();

        return response()->json(null, 204);
    }

    public function restore(Request $request, $id)
    {
        $authUser = $request->user();
        if (! $authUser->is_superadmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $billableValueCause = BillableValueCause::withTrashed()->findOrFail($id);
        $billableValueCause->restore();

        return response()->json($billableValueCause, 200);
    }
}

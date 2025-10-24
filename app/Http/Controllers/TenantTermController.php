<?php

namespace App\Http\Controllers;

use App\Models\TenantTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantTermController extends Controller
{
    /**
     * Get all terms for the current tenant
     */
    public function index(): JsonResponse
    {
        $tenant = config('app.tenant', env('TENANT', 'default'));
        $terms = TenantTerm::getTermsForTenant($tenant);

        return response()->json([
            'tenant' => $tenant,
            'terms' => $terms,
        ]);
    }

    /**
     * Get a specific term for the current tenant
     */
    public function show(string $key): JsonResponse
    {
        $tenant = config('app.tenant', env('TENANT', 'default'));
        $term = TenantTerm::getTermForTenant($tenant, $key);

        if ($term === null) {
            return response()->json([
                'message' => 'Term not found',
            ], 404);
        }

        return response()->json([
            'tenant' => $tenant,
            'key' => $key,
            'value' => $term,
        ]);
    }

    /**
     * Store or update a term for the current tenant
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
        ]);

        $tenant = config('app.tenant', env('TENANT', 'default'));

        $term = TenantTerm::setTermForTenant(
            $tenant,
            $request->key,
            $request->value,
            $request->description,
            $request->category
        );

        return response()->json([
            'message' => 'Term saved successfully',
            'term' => $term,
        ], 201);
    }

    /**
     * Update a specific term
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'value' => 'required',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
        ]);

        $tenant = config('app.tenant', env('TENANT', 'default'));

        $term = TenantTerm::where('tenant', $tenant)
            ->where('key', $key)
            ->first();

        if (! $term) {
            return response()->json([
                'message' => 'Term not found',
            ], 404);
        }

        $term->update([
            'value' => $request->value,
            'description' => $request->description,
            'category' => $request->category,
        ]);

        return response()->json([
            'message' => 'Term updated successfully',
            'term' => $term,
        ]);
    }

    /**
     * Delete a term
     */
    public function destroy(string $key): JsonResponse
    {
        $tenant = config('app.tenant', env('TENANT', 'default'));

        $deleted = TenantTerm::where('tenant', $tenant)
            ->where('key', $key)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'message' => 'Term not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Term deleted successfully',
        ]);
    }

    /**
     * Get terms by category
     */
    public function byCategory(string $category): JsonResponse
    {
        $tenant = config('app.tenant', env('TENANT', 'default'));

        $terms = TenantTerm::where('tenant', $tenant)
            ->where('category', $category)
            ->get()
            ->keyBy('key')
            ->map(function ($term) {
                return $term->value;
            })
            ->toArray();

        return response()->json([
            'tenant' => $tenant,
            'category' => $category,
            'terms' => $terms,
        ]);
    }
}

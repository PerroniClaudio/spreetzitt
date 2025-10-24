<?php

namespace App\Http\Controllers;

use App\Models\TicketTypeCategory;
use Illuminate\Http\Request;

class TicketTypeCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ticketTypeCategories = TicketTypeCategory::where('is_deleted', false)->get();

        return response($ticketTypeCategories, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
    public function show(TicketTypeCategory $ticketTypeCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketTypeCategory $ticketTypeCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketTypeCategory $ticketTypeCategory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketTypeCategory $ticketTypeCategory, Request $request)
    {
        $user = $request->user();
        if (! $user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $types = $ticketTypeCategory->ticketTypes;
        // Se ci sono tipi associati controlla se sono disabilitati e se lo sono tutti allora disabilita anche la categoria
        if (count($types) > 0) {
            foreach ($types as $type) {
                if (! $type->is_deleted) {
                    return response([
                        'message' => 'Active ticket-type associated',
                    ], 400);
                }
            }

            $ticketTypeCategory->update([
                'is_deleted' => true,
            ]);

            return response([
                'message' => 'Category deleted successfully',
            ], 200);

        }

        // Se non ci sono tipi associati elimina la categoria
        $deleted = TicketTypeCategory::destroy($ticketTypeCategory['id']);
        if ($deleted) {
            return response(['message' => 'Category deleted successfully'], 200);
        } else {
            return response(['message' => 'Category not found'], 404);
        }

        return response([
            'message' => 'Category deleted successfully',
        ], 200);
    }
}

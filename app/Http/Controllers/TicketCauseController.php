<?php

namespace App\Http\Controllers;

use App\Models\TicketCause;
use Illuminate\Http\Request;

class TicketCauseController extends Controller
{
    public function index(Request $request)
    {
        $query = TicketCause::query()->orderBy('name');

        return response()->json(['causes' => $query->get()]);
    }

    public function all(Request $request)
    {
        $query = TicketCause::withTrashed()->orderBy('name');

        return response()->json(['causes' => $query->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $ticketCause = TicketCause::create($validated);

        return response()->json($ticketCause, 201);
    }

    public function show(TicketCause $ticketCause)
    {
        return response()->json($ticketCause);
    }

    public function update(Request $request, TicketCause $ticketCause)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $ticketCause->update($validated);

        return response()->json($ticketCause);
    }

    public function destroy(TicketCause $ticketCause)
    {
        $ticketCause->delete();

        return response()->json(['message' => 'Ticket cause soft deleted'], 200);
    }

    public function forceDestroy($id)
    {
        $ticketCause = TicketCause::withTrashed()->findOrFail($id);
        $ticketCause->forceDelete();

        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $ticketCause = TicketCause::withTrashed()->findOrFail($id);
        $ticketCause->restore();

        return response()->json($ticketCause, 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\SendUpdateEmail;
use App\Models\TicketStatusUpdate;
use Illuminate\Http\Request;

class TicketStatusUpdateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id, Request $request)
    {
        $ticketStatusUpdates = TicketStatusUpdate::where('ticket_id', $id)->with(['user'])->get();

        if (! $request->user()->is_admin) {
            foreach ($ticketStatusUpdates as $ticketStatusUpdate) {
                if ($ticketStatusUpdate->user->is_admin) {
                    $ticketStatusUpdate->makeHidden(['user_id']);
                    $ticketStatusUpdate->user->makeHidden(['id', 'name', 'surname', 'email']);
                }
            }
        }

        return response([
            'statusUpdates' => $ticketStatusUpdates,
        ], 200);
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
    public function store($id, Request $request)
    {

        $user = $request->user();

        $fields = $request->validate([
            'status' => 'required|string',
        ]);

        $ticketStatusUpdate = TicketStatusUpdate::create([
            'ticket_id' => $id,
            'user_id' => $user->id,
            'content' => $fields['status'],
            'type' => 'status',
        ]);

        dispatch(new SendUpdateEmail($ticketStatusUpdate));

        return response([
            'ticketStatusUpdate' => $ticketStatusUpdate,
        ], 200);

    }

    /**
     * Display the specified resource.
     */
    public function show(TicketStatusUpdate $ticketStatusUpdate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketStatusUpdate $ticketStatusUpdate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketStatusUpdate $ticketStatusUpdate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketStatusUpdate $ticketStatusUpdate)
    {
        //
    }
}

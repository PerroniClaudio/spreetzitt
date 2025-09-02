<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
        $groups = Group::all();
        foreach ($groups as $group) {
            $group->level = $group->level();
        }
        
        return response([
            'groups' => $groups,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:groups',
            'email' => 'required|email',
        ]);

        $fields = $request->only((new Group())->getFillable());
        $group = Group::create($fields);        
        
        return response([
            'group' => $group,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group) {
        //

        return response([
            'group' => $group,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Group $group) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Group $group) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        if(!$request->user()->is_admin) {
            return response([
                'message' => 'Unauthorized',
            ], 403);
        }

        $fields = $request->only((new Group())->getFillable());

        $group->update($fields);

        return response([
            'group' => $group,
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group) {
        //
    }

    public function ticketTypes(Group $group) {
        $ticketTypes = $group->ticketTypes()->get();

        return response([
            'groupTicketTypes' => $ticketTypes,
        ], 200);
    }

    public function users(Group $group) {
        $users = $group->users()->get();

        return response([
            'users' => $users,
        ], 200);
    }

    public function updateUsers(Request $request) {
        $validated = $request->validate([
            'group_id' => 'required|integer',
            'users' => 'required|array',
        ]);

        $group = Group::find($validated['group_id']);

        $group->users()->sync($validated['users']);

        return response([
            'group' => $group,
        ], 200);
    }

    public function updateTypes(Request $request) {
        $validated = $request->validate([
            'group_id' => 'required|integer',
            'ticket_types' => 'required|array',
        ]);

        $group = Group::find($validated['group_id']);

        $group->ticketTypes()->sync($validated['ticket_types']);

        return response([
            'group' => $group,
        ], 200);
    }
}

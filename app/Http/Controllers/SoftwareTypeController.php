<?php

namespace App\Http\Controllers;

use App\Models\SoftwareType;
use Illuminate\Http\Request;

class SoftwareTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();

        if ($authUser->is_admin || $authUser->is_company_admin) {
            $softwareTypes = SoftwareType::all();

            return response([
                'softwareTypes' => $softwareTypes,
            ], 200);
        }

        $selectedCompany = $authUser->selectedCompany();
        $softwareTypes = SoftwareType::whereHas('software', function ($query) use ($selectedCompany) {
            $query->where('company_id', $selectedCompany ? $selectedCompany->id : 0);
        })->get();

        return response([
            'softwareTypes' => $softwareTypes,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to create software types',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string',
        ]);

        $data['name'] = strtoupper($data['name']);

        $softwareType = SoftwareType::create($data);

        return response([
            'softwareType' => $softwareType,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SoftwareType $softwareType)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to update software types',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string',
        ]);

        $softwareType->name = strtoupper($data['name']);
        $softwareType->save();

        return response([
            'softwareType' => $softwareType,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, SoftwareType $softwareType)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete software types',
            ], 403);
        }

        $softwareType->delete();

        return response([
            'message' => 'Software type deleted',
        ], 200);
    }
}

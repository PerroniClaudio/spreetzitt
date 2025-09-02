<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Office;
use Illuminate\Http\Request;

class OfficeController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
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
        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $fields = $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'number' => 'required|string',
            'zip_code' => 'required|string',
            'city' => 'required|string',
            'province' => 'required|string',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'is_legal' => 'required|boolean',
            'is_operative' => 'required|boolean',
            'company_id' => 'required|int',
        ]);

        $office = Office::create($fields);

        return response([
            'office' => $office,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Office $office) {
        $user = $request->user();

        if ($user["is_admin"] != 1 && !$user->companies()->where('companies.id', $office["company_id"])->exists() && ($office->company->data_owner_email != $request->user()->email)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        return response([
            'office' => $office,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Office $office) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Office $office) {
        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $fields = $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'number' => 'required|int',
            'zip_code' => 'required|string',
            'city' => 'required|string',
            'province' => 'required|string',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'is_legal' => 'required|boolean',
            'is_operative' => 'required|boolean',
            'company_id' => 'required|int',
        ]);

        $success = $office->update($fields);

        if (!$success) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        return response([
            'office' => $office,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id) {
        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $deleted_office = Office::destroy($id);

        if (!$deleted_office) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        return response([
            'deleted_office' => $id,
        ], 200);
    }

    /**
     * Get all offices of a company
     */
    // public function companyOffices(Company $company)
    // {
    //     $offices = $company->offices()->get();

    //     return response([
    //         'offices' => $offices,
    //     ], 200);
    // }

}

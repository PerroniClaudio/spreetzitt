<?php

namespace App\Http\Controllers;

use App\Models\HardwareType;
use Illuminate\Http\Request;

class HardwareTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $authUser = $request->user();
        // Anche i company admin possono vedere tutti i tipi di hardware perchè devono poter richiedere l'aggiunta di hardware che non hanno ancora in azienda.
        // Al massimo andranno implementati i gruppi di hardware visibili in azienda.
        if ($authUser->is_admin || $authUser->is_company_admin) {
            $hardwareTypes = HardwareType::all();

            return response([
                'hardwareTypes' => $hardwareTypes,
            ], 200);
        }

        $selectedCompany = $authUser->selectedCompany();
        $hardwareTypes = HardwareType::whereHas('hardware', function ($query) use ($selectedCompany) {
            $query->where('company_id', $selectedCompany ? $selectedCompany->id : 0);
        })->get();

        return response([
            'hardwareTypes' => $hardwareTypes,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response([
            'message' => 'Please use /api/store to create a new hardware type',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to create hardware types',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string',
        ]);

        $hardwareType = HardwareType::create($data);

        return response([
            'hardwareType' => $hardwareType,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(HardwareType $hardwareType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HardwareType $hardwareType)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to create hardware types',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string',
        ]);

        $hardwareType->name = $data['name'];
        $hardwareType->save();

        return response([
            'hardwareType' => $hardwareType,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, HardwareType $hardwareType)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware types',
            ], 403);
        }

        $hardwareType->delete();

        return response([
            'message' => 'Hardware type deleted',
        ], 200);
    }
}

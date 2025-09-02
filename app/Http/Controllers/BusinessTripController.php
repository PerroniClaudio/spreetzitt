<?php

namespace App\Http\Controllers;

use App\Models\BusinessTrip;
use App\Models\BusinessTripExpense;
use App\Models\BusinessTripTransfer;
use Illuminate\Http\Request;


class BusinessTripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $businessTrips = BusinessTrip::where('user_id', $user->id)->where('status', 0)->with(['user'])->orderBy('id', 'desc')->get();

        return response([
            'businessTrips' => $businessTrips,
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
    public function store(Request $request)
    {
        //

        $user = $request->user();

        $businessTrip = BusinessTrip::create([
            'user_id' => $user->id,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'status' => 0,
            'expense_type' => 0,
        ]);

        return response([
            'businessTrip' => $businessTrip,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(BusinessTrip $businessTrip)
    {
        //

        return response()->json($businessTrip);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BusinessTrip $businessTrip)
    {
        //

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BusinessTrip $businessTrip)
    {
        //

        $fields = $request->validate([
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'status' => 'required|integer',
            'expense_type' => 'required|integer',
        ]);

        $businessTrip->update($fields);

        return response([
            'businessTrip' => $businessTrip,
        ], 200);
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BusinessTrip $businessTrip)
    {
        //

        $businessTrip->update([
            'status' => 2,
        ]);

        return response([
            'message' => 'Business trip deleted successfully',
        ], 200);
    }

    public function getExpenses(BusinessTrip $businessTrip)
    {
        $expenses = BusinessTripExpense::where('business_trip_id', $businessTrip->id)->with(['company'])->get();

        return response([
            'expenses' => $expenses,
        ], 200);
    }

    public function storeExpense(BusinessTrip $businessTrip, Request $request)
    {

        $expense = BusinessTripExpense::create([
            'business_trip_id' => $businessTrip->id,
            'company_id' => $request->company_id,
            'payment_type' => $request->payment_type,
            'expense_type' => $request->expense_type,
            'amount' => $request->amount,
            'date' => $request->datetime,
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'zip_code' => $request->zip_code,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response([
            'expense' => $expense,
        ], 201);
    }

    public function getTransfers(BusinessTrip $businessTrip)
    {
        $transfers = BusinessTripTransfer::where('business_trip_id', $businessTrip->id)->with(['company'])->get();

        return response([
            'transfers' => $transfers,
        ], 200);
    }

    public function storeTransfer(BusinessTrip $businessTrip, Request $request)
    {
  
        $transfer = BusinessTripTransfer::create([
            'business_trip_id' => $businessTrip->id,
            'company_id' => $request->company_id,
            'date' => $request->datetime,
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'zip_code' => $request->zip_code,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response([
            'transfer' => $transfer,
        ], 201);
    }
}

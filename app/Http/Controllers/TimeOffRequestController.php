<?php

namespace App\Http\Controllers;

use App\Models\TimeOffRequest;
use App\Models\TimeOffType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeOffRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $requests = TimeOffRequest::with(['type', 'user'])->where('status', '<>', '4')->orderBy('id', 'asc')->get();
        
        $formattedRequests = [];
        $currentRequest = null;

        foreach( $requests as $key => $req ) {

            if($req->time_off_type_id == 11) {

                if($currentRequest) {
                    $currentRequest['date_to'] = $req->date_to;
                    if(isset($requests[$key + 1])) {
                        $nextBatchId = $requests[$key + 1]['batch_id'];
                        $currentBatchId = $req->batch_id;

                        if($nextBatchId != $currentBatchId) {
                            
                            $formattedRequests[] = $currentRequest;
                            $currentRequest = null;
                        }
                    } else {
                        $formattedRequests[] = $currentRequest;
                        $currentRequest = null;
                    }
                } else {
                    $currentRequest = $req;
                }
            } else {
                $formattedRequests[] = $req;
                $currentRequest = null;
            }

        }

        return response([
            'requests' => $formattedRequests,
           
        ]);

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

        $fields = $request->validate([
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'company_id' => 'required|int',
            'time_off_type_id' => 'required|int',
            'description' => 'required|string',
        ]);

        // L'orario di fine non può essere maggiore di quello di inizio

        if(strtotime($fields['date_to']) < strtotime($fields['date_from'])) {

            return response([
                'message' => 'La data di fine non può essere maggiore di quella di inizio',
            ], 400);

        }

        $fields['user_id'] = $user->id;

        $request = TimeOffRequest::create($fields);

        return response([
            'request' => $request
        ]);
    }

    public function storeBatch(Request $request) {

        $user = $request->user();

        // Controlla una per una che siano valide 

        $requests = json_decode($request->requests);

        $batch_id = uniqid();

        DB::beginTransaction();

        foreach( $requests as $time_off_request ) {

            $fields = [
                'date_from' => $time_off_request->date_from,
                'date_to' => $time_off_request->date_to,
                'time_off_type_id' => $time_off_request->time_off_type_id,
            ];

            $fields['user_id'] = $user->id;
            $fields['company_id'] = $user->selectedCompany()->id;
            $fields['batch_id'] = $batch_id;

            $existingRequest = TimeOffRequest::where('user_id', $user->id)
                ->where(function ($query) use ($fields) {
                    $query->whereBetween('date_from', [$fields['date_from'], $fields['date_to']])
                        ->orWhereBetween('date_to', [$fields['date_from'], $fields['date_to']]);
                })
                ->first();

            if ($existingRequest) {
                DB::rollBack();
                return response([
                    'message' => 'Hai già una richiesta di permesso in questo periodo',
                ], 400);
            }

            $request = TimeOffRequest::create($fields);

        }

        DB::commit();

        return response([
            'message' => 'Richieste di permesso create con successo'
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(TimeOffRequest $timeOffRequest)
    {
        //

        $requests = TimeOffRequest::with(['type', 'user'])->where('batch_id', $timeOffRequest->batch_id)->orderBy('id', 'asc')->get();

        return response([
            'requests' => $requests,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TimeOffRequest $timeOffRequest)
    {
        //

         
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeOffRequest $timeOffRequest)
    {
        //

        $fields = $request->validate([
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'company_id' => 'required|int',
            'time_off_type_id' => 'required|int',
        ]);

        $timeOffRequest->update($fields);

        return response([
            'message' => 'Richiesta di permesso aggiornata con successo'
        ], 201);
    }

    public function updateBatch(Request $request) {

        $user = $request->user();
        $requests = json_decode($request->requests);

        DB::beginTransaction();


        foreach( $requests as $time_off_request ) {

            $fields = [
                'date_from' => $time_off_request->date_from,
                'date_to' => $time_off_request->date_to,
                'id' => $time_off_request->id,
            ];

            $existingRequest = TimeOffRequest::where('user_id', $user->id)->where('id', '<>', $time_off_request->id)
                ->where(function ($query) use ($fields) {
                    $query
                        ->whereBetween('date_from', [$fields['date_from'], $fields['date_to']])
                        ->orWhereBetween('date_to', [$fields['date_from'], $fields['date_to']]);
                })
                ->first();

            if ($existingRequest) {
                DB::rollBack();
                return response([
                    'message' => 'Hai già una richiesta di permesso in questo periodo',
                    'matching' => $existingRequest,
                    'id' => $time_off_request->id
                ], 400);
            }

            $request = TimeOffRequest::where('id', $time_off_request->id)->update($fields);

        }

        DB::commit();

        return response([
            'message' => 'Richieste di permesso modificate con successo'
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeOffRequest $timeOffRequest)
    {
        //

        $timeOffRequest->update([
            'status' => '4'
        ]);

        return response([
            'message' => 'Richiesta di permesso cancellata con successo'
        ], 200);

    }

    public function types() {

        $types = TimeOffType::all();

        return response([
            'types' => $types
        ]);

    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceType;
use App\Models\TimeOffRequest;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        
        $user = $request->user();
    
        $attendances = Attendance::where('user_id', $user->id)->with(['company', 'attendanceType'])->orderBy('id', 'desc')->get();
 
        return response()->json($attendances);

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
            'date' => 'required|string',
            'time_in' => 'required|string',
            'time_out' => 'required|string',
            'company_id' => 'required|int',
            'attendance_type_id' => 'required|int',
        ]);

        // L'orario di fine non può essere maggiore di quello di inizio 

        if(strtotime($fields['time_out']) < strtotime($fields['time_in'])) {

            return response([
                'message' => 'L\'orario di fine non può essere maggiore di quello di inizio',
            ], 400);

        }

        // Non è possibile creare presenze nel futuro 

        if(strtotime($fields['date']) > strtotime(date('Y-m-d'))) {

            return response([
                'message' => 'Non è possibile creare presenze nel futuro',
            ], 400);

        }

        // Una presenza non può durare più di 4 ore

        $difference = (strtotime($fields['time_out']) - strtotime($fields['time_in'])) / 3600;

        if($difference > 4) {

            return response([
                'message' => 'Una presenza non può durare più di 4 ore',
            ], 400);

        }

        // Non ci devono essere richieste di ferie in quella presenza

        $timeOffRequests = TimeOffRequest::where('user_id', $user->id)->where('company_id', $fields['company_id'])->where('date_from', '<=', $fields['date'])->where('date_to', '>=', $fields['date'])->get();

        if(count($timeOffRequests) > 0) {

            return response([
                'message' => 'Non ci devono essere richieste di ferie o permesso nella presenza',
            ], 400);

        }

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'company_id' => $fields['company_id'],
            'date' => $fields['date'],
            'time_in' => $fields['time_in'],
            'time_out' => $fields['time_out'],
            'hours' => $difference,
            'attendance_type_id' => $fields['attendance_type_id'],
        ]);

        return response([
            'attendance' => $attendance,
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance)
    {
        
        return response()->json($attendance);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Attendance $attendance)
    {
        //

       

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        //

        $user = $request->user();

        $fields = $request->validate([
            'date' => 'required|string',
            'time_in' => 'required|string',
            'time_out' => 'required|string',
            'company_id' => 'required|int',

        ]);

        // L'orario di fine non può essere maggiore di quello di inizio 

        if(strtotime($fields['time_out']) < strtotime($fields['time_in'])) {

            return response([
                'message' => 'L\'orario di fine non può essere maggiore di quello di inizio',
            ], 400);

        }

        // Non è possibile creare presenze nel futuro 

        if(strtotime($fields['date']) > strtotime(date('Y-m-d'))) {

            return response([
                'message' => 'Non è possibile creare presenze nel futuro',
            ], 400);

        }

        // Una presenza non può durare più di 4 ore

        $difference = (strtotime($fields['time_out']) - strtotime($fields['time_in'])) / 3600;

        if($difference > 4) {

            return response([
                'message' => 'Una presenza non può durare più di 4 ore',
            ], 400);

        }

        $attendance->update([
            'user_id' => $user->id,
            'company_id' => $fields['company_id'],
            'date' => $fields['date'],
            'time_in' => $fields['time_in'],
            'time_out' => $fields['time_out'],
        ]);

        return response([
            'attendance' => $attendance,
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        //

        $attendance->delete();

        return response([
            'message' => 'Attendance deleted successfully',
        ], 200);
    }
    
    public function types() {

        $types = AttendanceType::all();

        return response([
            'types' => $types,
        ], 200);

    }
}

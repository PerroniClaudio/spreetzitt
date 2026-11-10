<?php

namespace App\Http\Controllers;

use App\Services\AdminTicketActivity;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminTicketActivityController extends Controller
{
    public function index(Request $request, AdminTicketActivity $activity)
    {
        abort_unless($request->user()->is_admin, 403);

        $rules = [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
        if ($request->user()->is_superadmin) {
            $rules['admin_user_id'] = ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', 1)];
        }
        $fields = $request->validate($rules);

        // Ignore client-supplied user IDs for ordinary admins.
        $adminId = $request->user()->is_superadmin
            ? ($fields['admin_user_id'] ?? $request->user()->id)
            : $request->user()->id;
        $start = CarbonImmutable::parse($fields['start_date'], config('app.timezone'))->startOfDay();
        $endExclusive = CarbonImmutable::parse($fields['end_date'], config('app.timezone'))->addDay()->startOfDay();
        if ($endExclusive->subDay()->greaterThan($start->addWeek())) {
            throw ValidationException::withMessages([
                'end_date' => 'La distanza tra le due date non può superare 7 giorni.',
            ]);
        }

        return response()->json([
            'admin_user_id' => (int) $adminId,
            'tickets' => $activity->search((int) $adminId, $start, $endExclusive, $fields['per_page'] ?? 25),
        ]);
    }
}

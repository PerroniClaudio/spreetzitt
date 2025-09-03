<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Company;
use App\Models\CustomUserGroup;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        $isAdminRequest = $authUser['is_admin'] == 1;

        if ($isAdminRequest) {
            $companies = Company::orderBy('name', 'asc')->get();
            $companies->makeHidden(['sla', 'sla_take_low', 'sla_take_medium', 'sla_take_high', 'sla_take_critical', 'sla_solve_low', 'sla_solve_medium', 'sla_solve_high', 'sla_solve_critical', 'sla_prob_take_low', 'sla_prob_take_medium', 'sla_prob_take_high', 'sla_prob_take_critical', 'sla_prob_solve_low', 'sla_prob_solve_medium', 'sla_prob_solve_high', 'sla_prob_solve_critical']);

            if (! $companies) {
                $companies = [];
            }
        } else {
            // Per utenti non admin, restituisci tutte le aziende collegate tramite la relazione companies()
            $companies = $authUser->companies()->get(['companies.id', 'companies.name']);
        }

        return response([
            'companies' => $companies,
        ], 200);
    }

    public function getMasterTickets(Company $company, Request $request)
    {
        $authUser = $request->user();
        $isAdminRequest = $authUser['is_admin'] == 1;

        if (! $isAdminRequest) {
            $user_companies = $authUser->companies()->get()->pluck('id')->toArray();

            // Controlla se l'utente è admin o se è un company admin della compagnia specificata
            if (! ($authUser->is_company_admin && in_array($company->id, $user_companies))) {
                return response([
                    'message' => 'Unauthorized',
                ], 401);
            }
        }

        // Recupera i ticket di tipo master associati alla compagnia

        $tickets = $company->tickets()
            ->whereHas('ticketType', function ($query) {
                $query->where('is_master', true);
            })
            ->with(['ticketType'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response([
            'master_tickets' => $tickets,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return response([
            'message' => 'Please use /api/store to create a new company',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $fields = $request->validate([
            'name' => 'required|string',
        ]);

        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Il campo sla non serve più. Quando si modificherà il database, togliere anche il campo da qui
        $newCompany = Company::create([
            'name' => $fields['name'],
            'sla' => 'vuoto',
        ]);

        return response([
            'company' => $newCompany,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1 && ! $user->companies()->where('companies.id', $id)->exists()) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $company = Company::where('id', $id)->first();

        if (! $company) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        /**
         * @disregard Intelephense non rileva il metodo temporaryUrl
         */
        $company->logo_url = $company->logo_url != null ? FileUploadController::generateSignedUrlForFile($company->logo_url, 70) : '';

        return response([
            'company' => $company,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
        return response([
            'message' => 'Please use /api/update to update an existing company',
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|int|exists:companies,id',
        ]);

        $user = $request->user();

        if (! $user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $company = Company::findOrFail($request->id);

        $updatedFields = $request->only($company->getFillable());
        $company->update($updatedFields);

        return response(['company' => $company], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (! $user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        // If it has users throw an error

        if (Company::findOrFail($id)->tickets()->count() > 0) {
            return response([
                'message' => 'tickets',
            ], 400);
        }

        if (Company::findOrFail($id)->ticketTypes()->count() > 0) {
            return response([
                'message' => 'ticket-types',
            ], 400);
        }

        if (Company::findOrFail($id)->users()->count() > 0) {
            return response([
                'message' => 'users',
            ], 400);
        }

        if (Company::findOrFail($id)->offices()->count() > 0) {
            return response([
                'message' => 'offices',
            ], 400);
        }

        $deleted_company = Company::destroy($id);

        if (! $deleted_company) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        return response([
            'deleted_company' => $id,
        ], 200);
    }

    public function offices(Company $company)
    {
        $offices = $company->offices()->get();

        return response([
            'offices' => $offices,
        ], 200);
    }

    public function admins(Company $company)
    {
        $users = $company->users()->where('is_company_admin', 1)->get();

        return response([
            'users' => $users,
        ], 200);
    }

    public function allusers(Company $company, Request $request)
    {
        $user = $request->user();

        // Se non è admin o non è della compagnia allora non è autorizzato
        if (! ($user['is_admin'] == 1 || $user->companies()->where('companies.id', $company['id'])->exists())) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
        // Esclude gli utenti disabilitati
        $users = $company->users()->where('users.is_deleted', false)->get();
        $users->makeHidden('microsoft_token');

        return response([
            'users' => $users,
        ], 200);
    }

    public function ticketTypes(Company $company, Request $request)
    {
        $isMassive = $request->query('is_massive');
        if ($isMassive) {
            $ticketTypes = $company->ticketTypes()->where('is_massive_enabled', 1)->with('category')->get();
        } else {
            $ticketTypes = $company->ticketTypes()->where('is_massive_enabled', 0)->with('category')->get();
        }

        return response([
            'companyTicketTypes' => $ticketTypes,
        ], 200);
    }

    public function brands(Company $company)
    {
        $brands = $company->brands()->each(function (Brand $brand) {
            $brand->withGUrl();
        });

        $brandsArray = [];
        foreach ($brands as $brand) {
            $brandsArray[] = $brand;
        }

        return response([
            'brands' => $brandsArray,
        ], 200);
    }

    public function getFrontendLogoUrl(Company $company)
    {
        $suppliers = Supplier::all()->toArray();

        // Prendi tutti i brand dei tipi di ticket associati all'azienda dell'utente
        $brands = $company->brands()->toArray();

        // Filtra i brand omonimo alle aziende interne ed utilizza quello dell'azienda interna con l'id piu basso
        $sameNameSuppliers = array_filter($suppliers, function ($supplier) use ($brands) {
            $brandNames = array_column($brands, 'name');

            return in_array($supplier['name'], $brandNames);
        });

        $selectedBrand = '';

        // Se ci sono aziende interne allora prende quella con l'id più basso e recupera il marchio omonimo, altrimenti usa il marchio con l'id più basso.
        if (! empty($sameNameSuppliers)) {
            usort($sameNameSuppliers, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });
            $selectedSupplier = reset($sameNameSuppliers);
            $selectedBrand = array_values(array_filter($brands, function ($brand) use ($selectedSupplier) {
                return $brand['name'] === $selectedSupplier['name'];
            }))[0];
        } else {
            usort($brands, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });

            $selectedBrand = reset($brands);
        }

        // Crea l'url
        $url = config('app.url').'/api/brand/'.$selectedBrand['id'].'/logo';

        // $url = $request->user()->company->frontendLogoUrl;

        return response([
            'urlLogo' => $url,
        ], 200);
    }

    public function tickets(Company $company, Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1 && ! $user->companies()->where('companies.id', $company['id'])->exists()) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $tickets = $company->tickets()->with(['ticketType'])->orderBy('created_at', 'desc')->get();

        if ($user['is_admin'] != 1) {
            foreach ($tickets as $ticket) {
                $ticket->makeHidden(['admin_user_id', 'group_id', 'priority', 'is_user_error', 'actual_processing_time']);
            }
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    // Orari azienda

    public function getWeeklyTimes(Company $company)
    {
        $weeklyTimes = $company->weeklyTimes()->get();

        if ($weeklyTimes->count() == 0) {

            $weeklyTimes = [];

            // Se non sono stati impostati generali di default con orario 09:00 - 18:00

            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

            foreach ($days as $day) {
                $weeklyTimes[] = [
                    'day' => $day,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                ];
            }

            // Sabato e domenica di default vengono inseriti a 00:00 - 00:00

            $weeklyTimes[] = [
                'day' => 'saturday',
                'start_time' => '00:00',
                'end_time' => '00:00',
            ];

            $weeklyTimes[] = [
                'day' => 'sunday',
                'start_time' => '00:00',
                'end_time' => '00:00',
            ];

            foreach ($weeklyTimes as $weeklyTime) {
                $company->weeklyTimes()->create($weeklyTime);
            }
        }

        $weeklyTimes = $company->weeklyTimes()->get();

        return response([
            'weeklyTimes' => $weeklyTimes,
        ], 200);
    }

    public function editWeeklyTime(Request $request)
    {
        $request->validate([
            'company_id' => 'required|int|exists:companies,id',
            'day' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $weeklyTime = Company::findOrFail($request->company_id)->weeklyTimes()->where('day', $request->day)->first();

        $weeklyTime->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return response(['weeklyTime' => $weeklyTime], 200);
    }

    public function uploadLogo(Company $company, Request $request)
    {

        if (! $company) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        if ($request->file('file') != null) {

            $file = $request->file('file');
            $file_name = time().'_'.$file->getClientOriginalName();

            $path = 'company/'.$company->id.'/logo/';

            $storeFile = FileUploadController::storeFile($file, $path, $file_name);

            // $company = Company::find($id);
            $company->update([
                'logo_url' => $storeFile,
            ]);

            return response()->json([
                'company' => $company,
            ]);
        }
    }

    // Gruppi custom

    public function getCustomUserGroups(Company $company)
    {

        $customUserGroups = $company->customUserGroups()->get();

        return response([
            'groups' => $customUserGroups,
        ], 200);
    }

    public function updateCustomUserGroup(CustomUserGroup $customUserGroup, Request $request)
    {

        $request->validate([
            'name' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user->is_company_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $customUserGroup->update([
            'name' => $request->name,
        ]);

        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function getCustomUserGroup(CustomUserGroup $customUserGroup)
    {
        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function storeCustomUserGroup(Request $request)
    {

        $request->validate([
            'name' => 'required|string|unique:custom_user_groups',
            'company_id' => 'required|int|exists:companies,id',
        ]);

        $user = $request->user();

        if (! $user->is_company_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $customUserGroup = CustomUserGroup::create([
            'name' => $request->name,
            'company_id' => $request->company_id,
            'created_by' => $user->id,
        ]);

        return response([
            'group' => $customUserGroup,
        ], 201);
    }

    public function getUsersForGroup(CustomUserGroup $customUserGroup)
    {

        $users = $customUserGroup->users()->get();

        return response([
            'users' => $users,
        ], 200);
    }

    public function getAvailableUsers(CustomUserGroup $customUserGroup)
    {

        $company = Company::find($customUserGroup->company->id);
        $users = $company->users()->where('is_deleted', 0)->whereDoesntHave('customUserGroups', function ($query) use ($customUserGroup) {
            $query->where('custom_user_groups.id', $customUserGroup->id);
        })->get();

        return response([
            'users' => $users,
        ], 200);
    }

    public function addUsersToGroup(Request $request)
    {

        $request->validate([
            'user_ids' => 'required|json',
            'custom_user_group_id' => 'required|int|exists:custom_user_groups,id',
        ]);

        $user = $request->user();

        if (! $user->is_company_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $customUserGroup = CustomUserGroup::findOrFail($request->custom_user_group_id);

        $user_ids = json_decode($request->user_ids);

        $customUserGroup->users()->syncWithoutDetaching($user_ids);

        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function removeUsersFromGroup(Request $request)
    {

        $request->validate([
            'user_ids' => 'required|json',
            'custom_user_group_id' => 'required|int|exists:custom_user_groups,id',
        ]);

        $user = $request->user();

        if (! $user->is_company_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $customUserGroup = CustomUserGroup::findOrFail($request->custom_user_group_id);
        $user_ids = json_decode($request->user_ids);

        $customUserGroup->users()->detach($user_ids);

        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function deleteCustomUserGroup(CustomUserGroup $customUserGroup)
    {

        if ($customUserGroup->users()->count() > 0) {
            return response([
                'message' => 'Group has users',
            ], 400);
        }

        $customUserGroup->delete();

        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function getCustomUserGroupTicketTypes(CustomUserGroup $customUserGroup)
    {

        $ticketTypes = $customUserGroup->ticketTypes()->get();

        return response([
            'ticketTypes' => $ticketTypes,
        ], 200);
    }

    public function getAvailableTicketTypes(CustomUserGroup $customUserGroup)
    {

        $company = Company::find($customUserGroup->company->id);

        $ticketTypes = $company->ticketTypes()->where('is_deleted', 0)->whereDoesntHave('customGroups', function ($query) use ($customUserGroup) {
            $query->where('custom_user_groups.id', $customUserGroup->id);
        })->get();

        return response([
            'ticketTypes' => $ticketTypes,
        ], 200);
    }

    public function addTicketTypesToGroup(Request $request)
    {

        $request->validate([
            'ticket_type_ids' => 'required|json',
            'custom_user_group_id' => 'required|int|exists:custom_user_groups,id',
        ]);

        $user = $request->user();

        if (! $user->is_company_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $customUserGroup = CustomUserGroup::findOrFail($request->custom_user_group_id);

        $ticket_type_ids = json_decode($request->ticket_type_ids);
        $customUserGroup->ticketTypes()->syncWithoutDetaching($ticket_type_ids);

        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function removeTicketTypesFromGroup(Request $request)
    {

        $request->validate([
            'ticket_type_ids' => 'required|json',
            'custom_user_group_id' => 'required|int|exists:custom_user_groups,id',
        ]);

        $user = $request->user();

        if (! $user->is_company_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $customUserGroup = CustomUserGroup::findOrFail($request->custom_user_group_id);
        $ticket_type_ids = json_decode($request->ticket_type_ids);
        $customUserGroup->ticketTypes()->detach($ticket_type_ids);

        return response([
            'group' => $customUserGroup,
        ], 200);
    }

    public function updateDelayWarning(Company $company, Request $request)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'reading_delay_start' => 'nullable|date_format:H:i',
            'reading_delay_notice' => 'required_if:reading_delay_start,!=,null|string',
        ]);

        $company->update([
            'reading_delay_start' => $request->reading_delay_start,
            'reading_delay_notice' => $request->reading_delay_notice,
        ]);

        return response([
            'company' => $company,
            'reading_delay_start' => $company->reading_delay_start,
            'reading_delay_notice' => $company->reading_delay_notice,
            'request_reading_delay_start' => $request->reading_delay_start,
            'request_reading_delay_notice' => $request->reading_delay_notice,
        ], 200);
    }
}

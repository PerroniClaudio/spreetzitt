<?php

namespace App\Http\Controllers;

use App\Exports\UserTemplateExport;
use App\Imports\UsersImport;
use App\Jobs\SendWelcomeEmail;
use App\Models\ActivationToken;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PragmaRX\Google2FA\Google2FA;

class UserController extends Controller
{
    //

    public function me()
    {

        $user = auth()->user();

        return response([
            'user' => $user,
        ], 200);
    }

    public function store(Request $request)
    {
        $requestUser = $request->user();

        $fields = $request->validate([
            'company_id' => $requestUser['is_admin'] == 1 ? 'required|int' : 'nullable|int',
            'name' => 'required|string',
            'email' => 'required|string',
            'surname' => 'required|string',
        ]);

        $userCompanyIds = $requestUser->companies()->pluck('companies.id')->toArray();
        // if (!($requestUser["is_admin"] == 1 || (in_array($fields["company_id"], $userCompanyIds) && $requestUser["is_company_admin"] == 1))) {
        if (! ($requestUser['is_admin'] == 1 || ($requestUser['is_company_admin'] == 1))) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($requestUser['is_admin'] != 1) {
            $fields['company_id'] = $requestUser->selectedCompany();
        }

        // Se si modifica qualcosa da questo punto in poi bisogna modificare anche in UsersImport.php
        $newUser = User::create([
            // 'company_id' => $fields['company_id'],
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make(Str::password()),
            'surname' => $fields['surname'],
            'phone' => $request['phone'] ?? null,
            'city' => $request['city'] ?? null,
            'zip_code' => $request['zip_code'] ?? null,
            'address' => $request['address'] ?? null,
            'is_company_admin' => $request['is_company_admin'] ?? 0,
        ]);

        $newUser->companies()->attach($fields['company_id']);

        $activation_token = ActivationToken::create([
            // 'token' => Hash::make(Str::random(32)),
            'token' => Str::random(20).time(),
            'uid' => $newUser['id'],
            'status' => 0,
        ]);

        // Inviare mail con url: frontendBaseUrl + /support/set-password/ + activation_token['token]
        $url = env('FRONTEND_URL').'/support/set-password/'.$activation_token['token'];
        dispatch(new SendWelcomeEmail($newUser, $url));

        return response([
            'user' => $newUser,
        ], 201);
    }

    /**
     * Mostra i dati dell'utente.
     */
    public function show($id, Request $request)
    {
        $authUser = $request->user();

        $user = User::where('id', $id)->with(['companies'])->first();

        // Se non è l'utente stesso, un admin o company_admin e della stessa compagnia allora non è autorizzato
        if (! ($authUser['is_admin'] == 1 || ($authUser['id'] == $id) || ($user && (
            // $user["company_id"] == $authUser["company_id"]
            $authUser->selectedCompany() && $user->hasCompany($authUser->selectedCompany()->id)
        ) && ($authUser['is_company_admin'] == 1)))) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        return response([
            'user' => $user,
        ], 200);
    }

    /**
     * Attiva l'utenza assegnandogli la password scelta.
     */
    public function activateUser(Request $request)
    {
        $fields = $request->validate([
            'token' => 'required|string|exists:activation_tokens,token',
            'email' => 'required|string|exists:users,email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request['email'])->first();

        // Per non far sapere che l'utente esiste si può modificare in unauthorized
        if (! $user) {
            return response([
                // 'message' => 'User not found',
                'message' => 'Unauthorized',
            ], 404);
        }

        if ($user['is_deleted'] == 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // l'activation token nel db deve avere token, uid, status = 0
        $token = ActivationToken::where('token', $request['token'])->first();
        if ($token['uid'] != $user['id'] || $token['used'] != 0) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Controllare se la password rispetta i requisiti e poi aggiornare la password dell'utente
        $password = $fields['password'];
        $pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{10,}$/";

        if (! preg_match($pattern, $password)) {
            return response([
                'message' => 'Invalid password',
            ], 400);
        }

        $updated = $user->update([
            'password' => Hash::make($fields['password']),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        if (! $updated) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        $token->update([
            'used' => 1,
        ]);

        Auth::login($user);

        return response([
            'user' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $fields = $request->validate([
            'id' => 'required|int|exists:users,id', // TODO: 'id' => 'required|int|exists:users,id
            // 'company_id' => 'required|int',
            'name' => 'required|string',
            'email' => 'required|string',
            'surname' => 'required|string',
            'is_superadmin' => 'sometimes|boolean',
        ]);

        $req_user = $request->user();

        $user = User::where('id', $request['id'])->first();

        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        // Se non è admin o non è della compagnia e company_admin allora non è autorizzato
        if (! ($req_user['is_admin'] == 1 || ($user->companies()->where('companies.id', $req_user->selectedCompany()->id)->exists() && $req_user['is_company_admin'] == 1))) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Per essere superadmin deve essere prima admin
        if ($fields['is_superadmin'] == 1 && ! $user['is_admin']) {
            return response([
                'message' => 'A user must be an admin to be a superadmin',
            ], 400);
        }
        // Solo i superadmin possono modificare lo stato di superadmin
        if (isset($fields['is_superadmin']) && ($fields['is_superadmin'] != $user['is_superadmin']) && ($req_user['is_superadmin'] != 1)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
        // Se c'è solo un utente superadmin non si può togliere lo stato di superadmin
        if (isset($fields['is_superadmin']) && ($fields['is_superadmin'] == 0) && ($user['is_superadmin'] == 1)) {
            $superadminCount = User::where('is_superadmin', 1)->count();
            if ($superadminCount <= 1) {
                return response([
                    'message' => 'There must be at least one superadmin',
                ], 400);
            }
        }

        $updatedFields = [];

        $userFields = $user->getFillable();

        foreach ($request->all() as $fieldName => $fieldValue) {
            if (in_array($fieldName, $userFields)) {
                $updatedFields[$fieldName] = $fieldValue;
            }
        }

        $user->update([
            'is_company_admin' => $updatedFields['is_company_admin'],
            // 'company_id' => $updatedFields['company_id'],
            'name' => $updatedFields['name'],
            'surname' => $updatedFields['surname'],
            'email' => $updatedFields['email'],
            'phone' => $updatedFields['phone'],
            'address' => $updatedFields['address'],
            'city' => $updatedFields['city'],
            'zip_code' => $updatedFields['zip_code'],
            // 'password' => $updatedFields['password'] ?? $user->password,
            'is_superadmin' => $updatedFields['is_superadmin'] ?? $user['is_superadmin'],
        ]);

        return response([
            'user' => $user,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //Solo gli admin possono eliminare (disabilitare) le utenze
        $req_user = $request->user();

        if ($req_user['is_admin'] == 1 && $id) {
            // In ogni caso si disabilita l'utente, senza eliminarlo.
            $user = User::where('id', $id)->first();
            if($user->is_superadmin == 1){
                if($req_user->is_superadmin != 1){
                    return response([
                        'message' => 'Unauthorized',
                    ], 401);
                }
                // Se c'è solo un utente superadmin non si può disabilitare
                $superadminCount = User::where('is_superadmin', 1)->count();
                if ($superadminCount <= 1) {
                    return response([
                        'message' => 'There must be at least one superadmin',
                    ], 400);
                }
            }

            $disabled = $user->update([
                'is_deleted' => true,
            ]);
            if ($disabled) {
                return response([
                    'deleted_user' => $id,
                ], 200);
            }

            return response([
                'message' => 'Error',
            ], 400);
        }
    }

    // Riabilitare utente disabilitato
    public function enable($id, Request $request)
    {
        $req_user = $request->user();

        if ($req_user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
        if (! $id) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        $user = User::where('id', $id)->first();

        if($user->is_superadmin == 1 && ($req_user->is_superadmin != 1)){
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
            
        $enabled = $user->update([
            'is_deleted' => 0,
        ]);
        if (! $enabled) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        return response([
            'enabled_user' => $id,
        ], 200);
    }

    public function ticketTypes(Request $request)
    {

        $user = $request->user();

        // Se l'utente è admin allora prende tutti i ticket types di tutti i gruppi associati all'utente, altrimenti solo quelli della sua compagnia
        if ($user['is_admin'] == 1) {
            $ticketTypes = collect();
            foreach ($user->groups as $group) {
                $ticketTypes = $ticketTypes->concat($group->ticketTypes()->with(['category', 'slaveTypes'])->get());
            }
        } else {
            $selectedCompany = $user->selectedCompany();
            $ticketTypes = $selectedCompany ? $selectedCompany->ticketTypes()->where('is_custom_group_exclusive', false)->with(['category', 'slaveTypes'])->get() : collect();

            $customGroups = $user->customUserGroups()->get();
            foreach ($customGroups as $customGroup) {
                $ticketTypes = $ticketTypes->concat($customGroup->ticketTypes()->with(['category', 'slaveTypes'])->get());
            }

            // Gli utenti normali non devono vedere i ticket master (operazioni strutturate), mentre i company_admin possono solo vedere il dettaglio, ma non aprirli.
            // if (! $user->is_company_admin || ($request->get('new_ticket') == 'true')) {
            //     $ticketTypes = $ticketTypes->filter(function ($ticketType) {
            //         return ! $ticketType->is_master;
            //     });
            // }
        }

        if ($user->is_superadmin == false) {
            $ticketTypes->makeHidden(['hourly_cost', 'hourly_cost_expires_at']);
        }

        return response([
            'ticketTypes' => $ticketTypes->values()->all(),
        ], 200);
    }

    // public function adminTicketTypes(Request $request) {

    //     $user = $request->user();

    //     if($user["is_admin"] == 1){
    //         $ticketTypes = collect();
    //         foreach ($user->groups as $group) {
    //             $ticketTypes = $ticketTypes->concat($group->ticketTypes()->with('category')->get());
    //         }
    //     }

    //     return response([
    //         'ticketTypes' => $ticketTypes || [],
    //     ], 200);

    // }

    public function test(Request $request)
    {

        return response([
            'test' => $request,
        ], 200);
    }

    // Restituisce gli id degli admin (serve per vedere se un messaggio va mostrato come admin o meno).
    // Controlla se l'utente che fa la richiesta è admin, se lo è restituisce gli id degli admin, altrimenti restituisce [].
    public function adminsIds(Request $request)
    {
        $isAdminRequest = $request->user()['is_admin'] == 1;

        if ($isAdminRequest) {
            $users = User::where('is_admin', 1)->get();
            $ids = $users->map(function ($user) {
                return $user->id;
            });
        } else {
            $ids = [];
        }

        return response([
            'ids' => $ids,
        ], 200);
    }

    public function allAdmins(Request $request)
    {
        $isAdminRequest = $request->user()['is_admin'] == 1;

        if ($isAdminRequest) {
            $users = User::where('is_admin', 1)->get();
        } else {
            $users = null;
        }

        return response([
            'admins' => $users,
        ], 200);
    }

    public function allUsers(Request $request)
    {
        $isAdminRequest = $request->user()['is_admin'] == 1;
        if ($isAdminRequest) {
            $users = User::with(['companies:id,name'])->get();
            $users->makeHidden(['microsoft_token']);
            if (! $users) {
                $users = [];
            }
        } else {
            $users = [];
        }

        return response([
            'users' => $users,
        ], 200);
    }

    public function getName($id, Request $request)
    {
        $authUser = $request->user();
        $user = User::where('id', $id)->first();

        $authUserSelectedCompanyId = $authUser->selectedCompany()->id ?? null;
        $company = Company::find($authUserSelectedCompanyId);

        if (! $request->user()['is_admin']
            && ! $user->hasCompany($authUserSelectedCompanyId)
            && ! $user->companies()->where('data_owner_email', $authUser->email)->exists()) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        return response([
            'name' => ($user['name'] ?? '').' '.($user['surname'] ?? ''),
        ], 200);
    }

    public function getFrontendLogoUrl(Request $request)
    {
        $suppliers = Supplier::all()->toArray();

        $authUser = $request->user();

        if($authUser['is_admin'] == 1){
            $brands = \App\Models\Brand::all()->toArray();
        } else {
            // Prendi tutti i brand dei tipi di ticket associati all'azienda dell'utente
            $selectedCompany = $request->user()->selectedCompany();
            $brands = $selectedCompany ? $selectedCompany->brands()->toArray() : [];
        }

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

    public function exportTemplate()
    {
        $name = 'users_import_template_'.time().'.xlsx';

        return Excel::download(new UserTemplateExport, $name);
    }

    public function importUsers(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (! ($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX or XLS file.',
                ], 400);
            }

            Excel::import(new UsersImport, $file, 'xlsx');
        }

        return response([
            'message' => 'Success',
        ], 200);
    }

    public function twoFactorChallenge(Request $request)
    {
        $user = Auth::user();

        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return response([
                'message' => 'Invalid code',
            ], 401);
        }

        return response([
            'success' => true,
        ], 200);
    }

    public function userTickets($userId, Request $request)
    {
        $authUser = $request->user();
        $user = User::where('id', $userId)->first();
        if (
            ! $authUser->is_admin
            && ! ($authUser->is_company_admin && ($user->companies()->whereIn('companies.id', $authUser->selectedCompany()->id)->exists()))
            && ! ($user->id == $authUser)
        ) {
            return response([
                'message' => 'You are not allowed to view this user tickets',
            ], 403);
        }

        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        if ($authUser->is_admin) {
            $tickets = $user->ownTicketsMerged();

            return response([
                'tickets' => $tickets,
            ], 200);
        }

        if ($authUser->is_company_admin) {
            $tickets = $user->ownTicketsMerged();

            foreach ($tickets as $ticket) {
                // Nascondere i dati utente se è stato aperto dal supporto
                if ($ticket->user->is_admin) {
                    $ticket->user->id = 1;
                    $ticket->user->name = 'Supporto';
                    $ticket->user->surname = '';
                    $ticket->user->email = 'Supporto';
                }
            }

            return response([
                'tickets' => $tickets,
            ], 200);
        }
    }

    public function companies(Request $request)
    {
        $user = $request->user();

        return response([
            'companies' => $user->companies()->get(),
        ], 200);
    }

    public function setActiveCompany(Request $request)
    {
        $request->validate([
            'companyId' => 'required|integer|exists:companies,id',
        ]);

        $user = $request->user();

        $user_companies = $user->companies()->get()->pluck('id')->toArray();
        // Controlla se l'utente appartiene alla compagnia
        if (! in_array($request->companyId, $user_companies)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Elimina tutte le cache che contengono 'user_' . $user->id . '_'
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            foreach ($redis->keys('*user_'.$user->id.'_*') as $key) {
                $redis->del($key);
            }
        } else {
            // Per altri driver di cache, usa la flush o tags se supportati
            Cache::flush();
        }

        // Salva il company_id nella sessione
        session(['selected_company_id' => $request->companyId]);

        return response([
            'success' => true,
            'selected_company_id' => $request->companyId,
        ], 200);
    }

    public function resetActiveCompany(Request $request)
    {
        $user = $request->user();

        // Elimina tutte le cache che contengono 'user_' . $user->id . '_'
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            foreach ($redis->keys('*user_'.$user->id.'_*') as $key) {
                $redis->del($key);
            }
        } else {
            // Per altri driver di cache, usa la flush o tags se supportati
            Cache::flush();
        }

        // Salva il company_id nella sessione
        session(['selected_company_id' => null]);

        return response([
            'success' => true,
            'selected_company_id' => null,
        ], 200);
    }

    public function companiesForUser($id, Request $request)
    {
        $authUser = $request->user();

        // Se non è admin o company_admin allora non è autorizzato
        if (! ($authUser['is_admin'] == 1 || ($authUser['id'] == $id) || ($authUser['is_company_admin'] == 1))) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = User::where('id', $id)->with(['companies'])->first();

        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        return response([
            'companies' => $user->companies,
        ], 200);
    }

    public function addCompaniesForUser($id, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser['is_admin']) {
            // Se non è admin o company_admin allora non è autorizzato
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = User::where('id', $id)->first();

        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $fields = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $user->companies()->syncWithoutDetaching($fields['company_id']);

        return response([
            'message' => 'Companies added successfully',
            'success' => true,
            'companies' => $user->companies()->get(),
        ], 200);
    }

    public function deleteCompaniesForUser($id, Company $company, Request $request)
    {
        $authUser = $request->user();

        if (! $authUser['is_admin']) {
            // Se non è admin o company_admin allora non è autorizzato
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = User::where('id', $id)->first();

        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $user->companies()->detach($company->id);

        return response([
            'message' => 'Companies deleted successfully',
            'success' => true,
            'companies' => $user->companies()->get(),
        ], 200);
    }
}

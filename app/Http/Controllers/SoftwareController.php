<?php

namespace App\Http\Controllers;

use App\Exports\SoftwareAssignationTemplateExport;
use App\Exports\SoftwareDeletionTemplateExport;
use App\Exports\SoftwareExport;
use App\Exports\SoftwareLogsExport;
use App\Exports\SoftwareTemplateExport;
use App\Http\Controllers\FileUploadController;
use App\Imports\SoftwareAssignationsImport;
use App\Imports\SoftwareDeletionsImport;
use App\Imports\SoftwareImport;
use App\Models\Company;
use App\Models\Software;
use App\Models\SoftwareAttachment;
use App\Models\SoftwareAuditLog;
use App\Models\SoftwareType;
use App\Models\TypeFormFields;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SoftwareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        
        if ($authUser->is_admin) {
            $softwareList = Software::with([
                'softwareType', 
                'company', 
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email', 'users.is_admin');
                },
            ])->get();

            return response([
                'softwareList' => $softwareList,
            ], 200);
        }

        if ($authUser->is_company_admin) {
            $selectedCompany = $authUser->selectedCompany();
            $softwareList = $selectedCompany 
                ? Software::where('company_id', $selectedCompany->id)->with([
                    'softwareType', 
                    'company', 
                    'users' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.surname', 'users.email', 'users.is_admin');
                    },
                ])->get() 
                : collect();

            return response([
                'softwareList' => $softwareList,
            ], 200);
        }

        $selectedCompany = $authUser->selectedCompany();
        $softwareList = $selectedCompany 
            ? Software::where('company_id', $selectedCompany->id)->whereHas('users', function ($query) use ($authUser) {
                $query->where('user_id', $authUser->id);
            })->with([
                'softwareType', 
                'company',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email', 'users.is_admin');
                },
            ])->get() 
            : collect();

        return response([
            'softwareList' => $softwareList,
        ], 200);
    }

    public function companySoftwareList(Request $request, Company $company)
    {
        $authUser = $request->user();
        
        if (!$authUser->is_admin && !($authUser->is_company_admin && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to view this software',
            ], 403);
        }

        $softwareQuery = Software::query();

        if (!!$authUser->is_admin) {
            $softwareQuery->withTrashed();
        }

        $softwareList = $softwareQuery->where('company_id', $company->id)
            ->with([
                'softwareType',
                'company',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                }
            ])
            ->get();

        return response([
            'softwareList' => $softwareList,
        ], 200);
    }

    public function userCompaniesSoftwareList(Request $request, User $user)
    {
        $authUser = $request->user();
        if (
            !$authUser->is_admin
        ) {
            return response([
                'message' => 'You are not allowed to view this software list',
            ], 403);
        }

        $userCompanyIds = $user->companies()->pluck('companies.id')->toArray();
        $softwareList = Software::whereIn('company_id', $userCompanyIds)
            ->with(['softwareType:id,name', 'company:id,name'])
            ->get()
            ->map(function ($software) {
                $software->users = $software->users()->pluck('user_id')->toArray();
                return $software;
            });
        
        return response([
            'softwareList' => $softwareList,
        ], 200);
    }

    public function formFieldSoftwareList(Request $request, TypeFormFields $typeFormField)
    {
        $authUser = $request->user();

        if (!$typeFormField) {
            return response([
                'message' => 'Type form field not found',
            ], 404);
        }

        $company = $typeFormField->ticketType->company;
        if (!$authUser->is_admin && !((bool) $company && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to view this software',
            ], 403);
        }

        $softwareList = [];

        // Costruisci la query di base
        if ($authUser->is_admin || $authUser->is_company_admin) {
            $query = Software::where('company_id', $company->id);
        } else {
            $query = $authUser->software();
        }
        
        // Aggiungi le relazioni
        $query->with(['softwareType', 'company']);

        // Esegui la query
        $softwareList = $query->get();

        return response([
            'softwareList' => $softwareList,
        ], 200);
    }

    public function softwareListWithTrashed(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this software',
            ], 403);
        }

        $softwareList = Software::withTrashed()->with(['softwareType', 'company'])->get();

        return response([
            'softwareList' => $softwareList,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to create software',
            ], 403);
        }

        $data = $request->validate([
            'vendor' => 'required|string',
            'product_name' => 'required|string',
            'version' => 'nullable|string',
            'activation_key' => 'nullable|string',
            'company_asset_number' => 'nullable|string|unique:software,company_asset_number',
            'is_exclusive_use' => 'required|boolean',
            'license_type' => 'nullable|string',
            'max_installations' => 'nullable|integer',
            'purchase_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'support_expiration_date' => 'nullable|date',
            'status' => 'required|string|in:' . implode(',', array_keys(config('app.software_statuses'))),
            'notes' => 'nullable|string',
            'company_id' => 'nullable|int|exists:companies,id',
            'software_type_id' => 'nullable|int|exists:software_types,id',
            'users' => 'nullable|array',
        ]);

        // Verificare le associazioni utenti
        if (isset($data['company_id']) && !empty($data['users'])) {
            $isFail = User::whereIn('id', $data['users'])
                ->whereDoesntHave('companies', function ($query) use ($data) {
                    $query->where('companies.id', $data['company_id']);
                })
                ->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected users do not belong to the specified company',
                ], 400);
            }
        }

        $users = $data['users'] ?? [];
        unset($data['users']);

        $software = Software::create($data);

        // Associare gli utenti
        if (!empty($users)) {
            $software->users()->attach($users, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return response([
            'software' => $software->load(['company', 'softwareType', 'users']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $softwareId)
    {
        $authUser = $request->user();
        $software = null;

        if ($authUser->is_admin) {
            $software = Software::withTrashed()->find($softwareId);
        } else {
            $software = Software::find($softwareId);
        }

        if (!$software) {
            return response([
                'message' => 'Software not found',
            ], 404);
        }

        if (
            !$authUser->is_admin
            && !($authUser->is_company_admin && $authUser->companies()->where('companies.id', $software->company_id)->exists())
            && !(in_array($authUser->id, $software->users->pluck('id')->toArray()))
        ) {
            return response([
                'message' => 'You are not allowed to view this software',
            ], 403);
        }

        $software->load([
            'company' => function ($query) {
                $query->select('id', 'name');
            },
            'softwareType',
            'users' => function ($query) {
                $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted');
            },
        ]);

        return response([
            'software' => $software,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Software $software)
    {
        $authUser = $request->user();

        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to edit software',
            ], 403);
        }

        $data = $request->validate([
            'vendor' => 'required|string',
            'product_name' => 'required|string',
            'version' => 'nullable|string',
            'activation_key' => 'nullable|string',
            'company_asset_number' => 'nullable|string|unique:software,company_asset_number,' . $software->id,
            'is_exclusive_use' => 'required|boolean',
            'license_type' => 'nullable|string',
            'max_installations' => 'nullable|integer',
            'purchase_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'support_expiration_date' => 'nullable|date',
            'status' => 'required|string|in:' . implode(',', array_keys(config('app.software_statuses'))),
            'notes' => 'nullable|string',
            'company_id' => 'nullable|int|exists:companies,id',
            'software_type_id' => 'nullable|int|exists:software_types,id',
            'users' => 'nullable|array',
        ]);

        // Verificare le associazioni utenti
        if (isset($data['company_id']) && !empty($data['users'])) {
            $isFail = User::whereIn('id', $data['users'])
                ->whereDoesntHave('companies', function ($query) use ($data) {
                    $query->where('companies.id', $data['company_id']);
                })
                ->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected users do not belong to the specified company',
                ], 400);
            }
        }

        if (!$software->is_exclusive_use && $data['is_exclusive_use'] && count($data['users']) > 1) {
            return response([
                'message' => 'Exclusive use software can be associated to no more than one user. Software not updated.',
            ], 400);
        }

        $oldCompanyId = $software->company_id;
        $users = $data['users'] ?? [];
        unset($data['users']);

        // Aggiorna il software
        $software->update($data);

        if ($software->company_id != $oldCompanyId) {
            $logType = $oldCompanyId ? ($software->company_id ? 'update' : 'delete') : 'create';
            $oldData = $oldCompanyId ? json_encode(['company_id' => $oldCompanyId]) : null;
            $newData = $software->company_id ? json_encode(['company_id' => $software->company_id]) : null;
            SoftwareAuditLog::create([
                'modified_by' => $authUser->id,
                'software_id' => $software->id,
                'log_subject' => 'software_company',
                'log_type' => $logType,
                'old_data' => $oldData,
                'new_data' => $newData,
            ]);
        }

        // Aggiorna gli utenti associati
        $usersToRemove = $software->users->pluck('id')->diff($users);
        $usersToAdd = collect($users)->diff($software->users->pluck('id'));

        foreach ($usersToAdd as $userId) {
            $software->users()->attach($userId, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $software->users()->detach($userId);
        }

        return response([
            'software' => $software,
        ], 200);
    }

    /**
     * Update the assigned users of the single software
     */
    public function updateSoftwareUsers(Request $request, Software $software)
    {
        $software = Software::find($software->id);
        if (!$software) {
            return response([
                'message' => 'Software not found',
            ], 404);
        }

        $authUser = $request->user();
        if (!($authUser->is_company_admin && $authUser->companies()->where('companies.id', $software->company_id)->exists()) && !$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update software users',
            ], 403);
        }

        $data = $request->validate([
            'users' => 'nullable|array',
        ]);

        $company = $software->company;

        if (!empty($data['users']) && !$company) {
            return response([
                'message' => 'Software must be associated with a company to add users',
            ], 404);
        }

        if ($company && !empty($data['users'])) {
            $isFail = User::whereIn('id', $data['users'])
                ->whereDoesntHave('companies', function ($query) use ($company) {
                    $query->where('companies.id', $company->id);
                })
                ->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected users do not belong to the specified company',
                ], 400);
            }
        }

        $users = User::whereIn('id', $data['users'])->get();
        if ($users->count() != count($data['users'])) {
            return response([
                'message' => 'One or more users not found',
            ], 404);
        }

        $usersToRemove = $software->users->pluck('id')->diff($data['users']);
        $usersToAdd = collect($data['users'])->diff($software->users->pluck('id'));

        // Solo l'admin può rimuovere associazioni software-user
        if (!$authUser->is_admin && count($usersToRemove) > 0) {
            return response([
                'message' => 'You are not allowed to remove users from software',
            ], 403);
        }

        // Il software ad uso esclusivo può essere associato a un solo utente
        if (
            $software->is_exclusive_use &&
            (count($usersToAdd) > 0 &&
                (($software->users->count() - count($usersToRemove) + count($usersToAdd)) > 1)
            )
        ) {
            return response([
                'message' => 'This software can be associated to only one user.',
            ], 400);
        }

        foreach ($usersToAdd as $userId) {
            $software->users()->attach($userId, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $software->users()->detach($userId);
        }

        return response([
            'message' => 'Software users updated successfully',
        ], 200);
    }

    /**
     * Update the assigned software of the single user
     */
    public function updateUserSoftware(Request $request, User $user)
    {
        $user = User::find($user->id);
        if (!$user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $authUser = $request->user();
        if (
            !$authUser->is_admin &&
            !(
                $authUser->is_company_admin &&
                $user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists()
            )
        ) {
            return response([
                'message' => 'You are not allowed to update user software',
            ], 403);
        }

        $data = $request->validate([
            'software' => 'nullable|array',
        ]);

        $userHasAtLeastOneCompany = $user->companies()->exists();

        if (!empty($data['software']) && !$userHasAtLeastOneCompany) {
            return response([
                'message' => 'User must be associated with a company to add software',
            ], 404);
        }

        if ($userHasAtLeastOneCompany && !empty($data['software'])) {
            $isFail = Software::whereIn('id', $data['software'])
                ->whereNotIn('company_id', $user->companies()->pluck('companies.id'))
                ->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected software do not belong to the user\'s company',
                ], 400);
            }
        }

        $software = Software::whereIn('id', $data['software'])->get();
        if ($software->count() != count($data['software'])) {
            return response([
                'message' => 'One or more software not found',
            ], 404);
        }

        // Se è admin software to remove va preso tutto, altrimenti dovrebbe essere filtrato con selectedCompany()
        if ($authUser->is_admin) {
            $softwareToRemove = $user->software->pluck('id')->diff($data['software']);
        } else {
            $softwareToRemove = $user->software()->where('company_id', $authUser->selectedCompany()->id)->pluck('id')->diff($data['software']);
        }

        $softwareToAdd = collect($data['software'])->diff($user->software->pluck('id'));

        // Solo l'admin può rimuovere associazioni software-user
        if (!$authUser->is_admin && count($softwareToRemove) > 0) {
            return response([
                'message' => 'You are not allowed to remove software from user',
            ], 403);
        }

        if (count($softwareToAdd) > 0) {
            foreach ($softwareToAdd as $softwareId) {
                $swToAdd = Software::find($softwareId);
                if ($swToAdd->is_exclusive_use && ($swToAdd->users->count() >= 1)) {
                    return response([
                        'message' => 'A selected software (' . $swToAdd->id . ') can only be associated to one user and has already been associated.',
                    ], 400);
                }
            }
        }

        foreach ($softwareToAdd as $softwareId) {
            $user->software()->attach($softwareId, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($softwareToRemove as $softwareId) {
            $user->software()->detach($softwareId);
        }

        return response([
            'message' => 'User assigned software updated successfully',
        ], 200);
    }

    public function deleteSoftwareUser($softwareId, $userId, Request $request)
    {
        $software = Software::findOrFail($softwareId);
        $user = User::findOrFail($userId);

        if (!$software) {
            return response([
                'message' => 'Software not found',
            ], 404);
        }
        if (!$user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $authUser = $request->user();
        // Adesso può farlo solo l'admin
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to delete software-user associations.',
            ], 403);
        }

        if (!$software->users->contains($user)) {
            return response([
                'message' => 'User not associated with software',
            ], 400);
        }

        $software->users()->detach($userId);

        return response()->json(['message' => 'User detached from software successfully'], 200);
    }

    public function userSoftwareList(Request $request, User $user)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin
            && !$user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists()
            && !($authUser->id == $user->id)
        ) {
            return response([
                'message' => 'You are not allowed to view this user software',
            ], 403);
        }

        // lato admin si vede tutto e lato utente si deve vedere solo quello della sua azienda
        if ($authUser->is_admin) {
            $softwareList = $user->software()->with([
                'softwareType',
                'company',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                },
            ])->get();
        } else {
            $softwareList = $user->software()
                ->where('company_id', $authUser->selectedCompany()->id)
                ->with([
                    'softwareType', 
                    'company',
                    'users' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                    },
                ])
                ->get();
        }

        return response([
            'softwareList' => $softwareList,
        ], 200);
    }

    public function fakeSoftwareField(Request $request)
    {
        // Dati fittizi statici per test
        $fakeCompany = (object) [
            'id' => 1,
            'name' => 'TestCompany',
        ];

        // Genera dati fittizi per SoftwareType
        $fakeSoftwareTypes = collect([
            (object) ['id' => 1, 'name' => 'Operating System'],
            (object) ['id' => 2, 'name' => 'Antivirus'],
            (object) ['id' => 3, 'name' => 'Office Suite'],
            (object) ['id' => 4, 'name' => 'Design Tool'],
            (object) ['id' => 5, 'name' => 'Development Tool'],
        ]);

        // Genera dati fittizi per Software
        $fakeSoftwareList = collect([
            [
                'id' => 1,
                'vendor' => 'Microsoft',
                'product_name' => 'Windows 11 Pro',
                'version' => '23H2',
                'company_id' => 1,
                'software_type_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'softwareType' => ['id' => 1, 'name' => 'Operating System'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 2,
                'vendor' => 'Microsoft',
                'product_name' => 'Office 365',
                'version' => 'E3',
                'company_id' => 1,
                'software_type_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
                'softwareType' => ['id' => 3, 'name' => 'Office Suite'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 3,
                'vendor' => 'Adobe',
                'product_name' => 'Creative Cloud',
                'version' => '2024',
                'company_id' => 1,
                'software_type_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
                'softwareType' => ['id' => 4, 'name' => 'Design Tool'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 4,
                'vendor' => 'JetBrains',
                'product_name' => 'PhpStorm',
                'version' => '2024.1',
                'company_id' => 1,
                'software_type_id' => 5,
                'created_at' => now(),
                'updated_at' => now(),
                'softwareType' => ['id' => 5, 'name' => 'Development Tool'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 5,
                'vendor' => 'Kaspersky',
                'product_name' => 'Endpoint Security',
                'version' => '12.0',
                'company_id' => 1,
                'software_type_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
                'softwareType' => ['id' => 2, 'name' => 'Antivirus'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
        ]);

        return response([
            'company' => $fakeCompany,
            'softwareTypes' => $fakeSoftwareTypes,
            'software' => $fakeSoftwareList,
        ], 200);
    }

    public function softwareTickets(Request $request, Software $software)
    {
        $authUser = $request->user();
        if (
            !$authUser->is_admin
            && !($authUser->is_company_admin && $authUser->companies()->where('companies.id', $software->company_id)->exists())
            && !($software->users->contains($authUser))
        ) {
            return response([
                'message' => 'You are not allowed to view this software tickets',
            ], 403);
        }

        if ($authUser->is_admin) {
            $tickets = $software->tickets()->with([
                'ticketType',
                'company' => function ($query) {
                    $query->select('id', 'name', 'logo_url');
                },
                'user' => function ($query) {
                    $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted')
                        ->with('companies:id');
                },
            ])->get();

            return response([
                'tickets' => $tickets,
            ], 200);
        }

        // Non sappiamo se il software può passare da un'azienda all'altra.
        if ($authUser->is_company_admin) {
            $tickets = $software->tickets()->where('company_id', $software->company_id)->with([
                'ticketType',
                'company' => function ($query) {
                    $query->select('id', 'name', 'logo_url');
                },
                'user' => function ($query) {
                    $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted')
                        ->with('companies:id');
                },
                'referer' => function ($query) {
                    $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted')
                        ->with('companies:id');
                },
                'refererIt' => function ($query) {
                    $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted')
                        ->with('companies:id');
                },
            ])->get();

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

        // Qui devono vedersi tutti i ticket collegati a questo software, aperti dall'utente o in cui è associato come utente interessato (referer)
        if ($software->users->contains($authUser)) {
            $tickets = $software->tickets()
                ->where('user_id', $authUser->id)
                ->orWhere('referer_id', $authUser->id)
                ->with([
                    'ticketType',
                    'company' => function ($query) {
                        $query->select('id', 'name', 'logo_url');
                    },
                    'user' => function ($query) {
                        $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted')
                            ->with('companies:id');
                    },
                    'referer' => function ($query) {
                        $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted')
                            ->with('companies:id');
                    },
                ])->get();

            foreach ($tickets as $ticket) {
                // Nascondere i dati utente se è stato aperto dal supporto
                if ($ticket->user->is_admin) {
                    $ticket->user->id = 1;
                    $ticket->user->name = 'Supporto';
                    $ticket->user->surname = '';
                    $ticket->user->email = 'Supporto';
                }
            }

            $tickets = $tickets->values()->toArray();

            return response([
                'tickets' => $tickets,
            ], 200);
        }

        return response([
            'message' => 'You are not allowed to view this software tickets',
        ], 403);
    }

    public function exportTemplate()
    {
        $name = 'software_import_template_' . time() . '.xlsx';

        return Excel::download(new SoftwareTemplateExport, $name);
    }

    public function exportAssignationTemplate()
    {
        $name = 'software_assignation_template_' . time() . '.xlsx';

        return Excel::download(new SoftwareAssignationTemplateExport, $name);
    }

    public function exportDeletionTemplate()
    {
        $name = 'software_deletion_template_' . time() . '.xlsx';

        return Excel::download(new SoftwareDeletionTemplateExport, $name);
    }

    public function importSoftware(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import software',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (!($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX file.',
                ], 400);
            }

            try {
                Excel::import(new SoftwareImport($authUser), $file, 'xlsx');
            } catch (\Exception $e) {
                return response([
                    'message' => 'An error occurred while importing the file. Please check the file and try again.'.($e->getMessage() ?? ''),
                    'error' => $e->getMessage(),
                ], 400);
            }
        }

        return response([
            'message' => 'Success',
        ], 200);
    }

    public function importSoftwareAssignations(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import software assignations',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (!($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX file.',
                ], 400);
            }

            try {
                Excel::import(new SoftwareAssignationsImport($authUser), $file, 'xlsx');
            } catch (\Exception $e) {
                return response([
                    'message' => 'An error occurred while importing the file. Please check the file and try again.'.($e->getMessage() ?? ''),
                    'error' => $e->getMessage(),
                ], 400);
            }
        }

        return response([
            'message' => 'Success',
        ], 200);
    }

    public function importSoftwareDeletions(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import software deletions',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (!($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX file.',
                ], 400);
            }

            try {
                Excel::import(new SoftwareDeletionsImport($authUser), $file, 'xlsx');
            } catch (\Exception $e) {
                return response([
                    'message' => 'An error occurred while importing the file. Please check the file and try again.'.($e->getMessage() ?? ''),
                    'error' => $e->getMessage(),
                ], 400);
            }
        }

        return response([
            'message' => 'Success',
        ], 200);
    }

    public function downloadUserSoftwareAssignmentPdf(Software $software, User $user, Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin
            && !($authUser->is_company_admin
                && (isset($software->company_id) && $software->company_id == ($authUser->selectedCompany()->id ?? null))
            )
        ) {
            return response([
                'message' => 'You are not allowed to download this document',
            ], 403);
        }

        if (!$software->users->contains($user)) {
            return response([
                'message' => 'User not associated with software',
            ], 400);
        }

        $softwareFileName = $software->company_asset_number
            ?? $software->product_name
            ?? $software->id;
        $userFileName = $user->surname
            ? ($user->name ? $user->surname.'_'.$user->name : $user->surname)
            : ($user->name ?? $user->id);
        $name = 'software_user_assignment_'.$softwareFileName.'_to_'.$userFileName.'_'.time().'.pdf';
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);

        $software->load(['softwareType', 'company']);

        $relation = $software->users()->wherePivot('user_id', $user->id)->first();

        // Gestione logo per sviluppo e produzione
        $google_url = null;
        if ($software->company) {
            $brand = $software->company->brands()->first();
            if ($brand) {
                $google_url = $brand->withGUrl()->logo_url;
            }
        }

        // Fallback per sviluppo se non c'è logo
        if (app()->environment('local', 'development') && empty($google_url)) {
            $google_url = null; // In sviluppo non mostriamo logo se mancante
        }

        $data = [
            'title' => $name,
            'software' => $software,
            'user' => $user,
            'relation' => $relation,
            'logo_url' => $google_url,
        ];

        Pdf::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true, // ✅ Abilita il caricamento di immagini da URL esterni
        ]);

        $pdf = Pdf::loadView('pdf.softwareuserassignment', $data);

        return $pdf->download($name);
    }

    public function destroy($softwareId, Request $request)
    {
        $user = $request->user();
        
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete software',
            ], 403);
        }

        $software = Software::findOrFail($softwareId);
        
        if (!$software) {
            return response([
                'message' => 'Software not found',
            ], 404);
        }
        
        $software->delete();
        
        SoftwareAuditLog::create([
            'modified_by' => $user->id,
            'software_id' => null,
            'log_subject' => 'software',
            'log_type' => 'delete',
            'old_data' => json_encode($software->toArray()),
            'new_data' => null,
        ]);

        return response([
            'message' => 'Software soft deleted successfully',
        ], 200);
    }

    public function destroyTrashed($softwareId, Request $request)
    {
        $user = $request->user();
        
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete software',
            ], 403);
        }

        $software = Software::withTrashed()->findOrFail($softwareId);
        
        if (!$software) {
            return response([
                'message' => 'Software not found',
            ], 404);
        }
        
        $software->forceDelete();
        
        SoftwareAuditLog::create([
            'modified_by' => $user->id,
            'software_id' => null,
            'log_subject' => 'software',
            'log_type' => 'permanent_delete',
            'old_data' => json_encode($software->toArray()),
            'new_data' => null,
        ]);

        return response([
            'message' => 'Software permanently deleted successfully',
        ], 200);
    }

    public function restoreTrashed($softwareId, Request $request)
    {
        $user = $request->user();
        
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to restore software',
            ], 403);
        }

        $software = Software::withTrashed()->findOrFail($softwareId);
        
        if (!$software) {
            return response([
                'message' => 'Software not found',
            ], 404);
        }
        
        $software->restore();

        return response([
            'message' => 'Software restored successfully',
            'software' => $software,
        ], 200);
    }

    public function getSoftwareLog($softwareId, Request $request)
    {
        $authUser = $request->user();
        
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this software log',
            ], 403);
        }

        $logs = SoftwareAuditLog::where('software_id', $softwareId)
            ->orWhere(function ($query) use ($softwareId) {
                $query->whereJsonContains('old_data->id', $softwareId)
                    ->orWhereJsonContains('new_data->id', $softwareId);
            })
            ->with('author')
            ->get();

        return response([
            'logs' => $logs,
        ], 200);
    }

    public function softwareLogsExport($softwareId, Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to export this software log',
            ], 403);
        }

        $name = 'software_'.$softwareId.'_logs_'.time().'.xlsx';

        return Excel::download(new SoftwareLogsExport($softwareId), $name);
    }

    /**
     * Export all software (admin only, can include trashed)
     */
    public function exportAllSoftware(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to export all software',
            ], 403);
        }

        $includeTrashed = true;
        $name = 'all_software_export_' . time() . '.xlsx';

        try {
            // Aumenta temporaneamente il limite di memoria
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);
            
            return Excel::download(new SoftwareExport(null, null, $includeTrashed), $name);
        } catch (\Exception $e) {
            Log::error('Software export failed: ' . $e->getMessage(), [
                'user_id' => $authUser->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response([
                'message' => 'Export failed. Please try again later.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export company software
     */
    public function exportCompanySoftware(Request $request, Company $company)
    {
        $authUser = $request->user();
        
        if (!$authUser->is_admin && !($authUser->is_company_admin && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to export this company\'s software',
            ], 403);
        }

        // Solo gli admin possono includere il trashed
        $includeTrashed = $authUser->is_admin;
        $name = 'company_' . $company->name . '_software_export_' . time() . '.xlsx';

        return Excel::download(new SoftwareExport($company->id, null, $includeTrashed), $name);
    }

    /**
     * Export user software
     */
    public function exportUserSoftware(Request $request, User $user)
    {
        $authUser = $request->user();
        
        // Controllo autorizzazioni
        if (!$authUser->is_admin 
            && !($authUser->is_company_admin && $user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists())
            && !($authUser->id == $user->id)
        ) {
            return response([
                'message' => 'You are not allowed to export this user\'s software',
            ], 403);
        }

        // Solo gli admin possono includere il trashed
        $includeTrashed = $authUser->is_admin;
        
        $userFileName = $user->surname 
            ? ($user->name ? $user->surname . '_' . $user->name : $user->surname)
            : ($user->name ?? $user->id);
        $name = 'user_' . $userFileName . '_software_export_' . time() . '.xlsx';
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);

        return Excel::download(new SoftwareExport($authUser->is_admin ? null : $authUser->selectedCompany()?->id, $user->id, $includeTrashed), $name);
    }

    /**
     * Get all attachments for software
     */
    public function getAttachments(Software $software, Request $request)
    {
        $authUser = $request->user();

        // Verifica permessi
        if (!$authUser->is_admin) {
            if ($authUser->is_company_admin) {
                // Company admin: può vedere solo software della sua azienda
                if ($software->company_id !== $authUser->selectedCompany()?->id) {
                    return response(['message' => 'Unauthorized'], 403);
                }
            } else {
                // User normale: può vedere solo se il software è assegnato a lui
                if (!$software->users()->where('user_id', $authUser->id)->exists()) {
                    return response(['message' => 'Unauthorized'], 403);
                }
            }
        }

        // Admin vede tutti gli allegati (anche soft deleted)
        // User e Company Admin vedono solo quelli non eliminati
        if ($authUser->is_admin) {
            $attachments = $software->attachments()->withTrashed()->with('uploader')->get();
        } else {
            $attachments = $software->attachments()->with('uploader')->get();
        }

        return response(['attachments' => $attachments], 200);
    }

    /**
     * Upload attachment for software
     */
    public function uploadAttachment(Software $software, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono caricare allegati
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $software->company_id !== $authUser->selectedCompany()?->id) {
            return response(['message' => 'Unauthorized'], 403);
        }

        $fields = $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'display_name' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        
        // Genera nome file univoco
        $extension = $file->getClientOriginalExtension();
        $uniqueName = time() . '_' . Str::random(10) . '.' . $extension;
        $path = 'software/' . $software->id;

        // Upload usando FileUploadController
        $filePath = FileUploadController::storeFile($file, $path, $uniqueName);

        // Crea record nel database
        $attachment = SoftwareAttachment::create([
            'software_id' => $software->id,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'display_name' => $fields['display_name'] ?? null,
            'file_extension' => $extension,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $authUser->id,
        ]);

        // Log audit
        SoftwareAuditLog::create([
            'log_subject' => 'software_attachment',
            'log_type' => 'create',
            'modified_by' => $authUser->id,
            'software_id' => $software->id,
            'old_data' => null,
            'new_data' => json_encode([
                'attachment_id' => $attachment->id,
                'filename' => $attachment->downloadFilename(),
            ]),
        ]);

        return response([
            'attachment' => $attachment->load('uploader'),
            'message' => 'File caricato con successo',
        ], 201);
    }

    /**
     * Upload multiple attachments for software
     */
    public function uploadAttachments(Software $software, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono caricare allegati
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $software->company_id !== $authUser->selectedCompany()?->id) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if (!$request->hasFile('files')) {
            return response(['message' => 'No files uploaded'], 400);
        }

        $files = $request->file('files');
        $uploadedAttachments = [];
        $count = 0;

        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file->isValid()) {
                    // Genera nome file univoco
                    $extension = $file->getClientOriginalExtension();
                    $uniqueName = time() . '_' . Str::random(10) . '.' . $extension;
                    $basePath = 'software/' . $software->id;

                    // Upload usando FileUploadController
                    $filePath = FileUploadController::storeFile($file, $basePath, $uniqueName);

                    // Crea record nel database
                    // display_name viene dal nome originale del file (senza estensione)
                    $originalFilename = $file->getClientOriginalName();
                    $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);

                    $attachment = SoftwareAttachment::create([
                        'software_id' => $software->id,
                        'file_path' => $filePath,
                        'original_filename' => $originalFilename,
                        'display_name' => $displayName,
                        'file_extension' => $extension,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $authUser->id,
                    ]);

                    $uploadedAttachments[] = $attachment;
                    $count++;
                }
            }
        } else {
            // Singolo file
            if ($files->isValid()) {
                $extension = $files->getClientOriginalExtension();
                $uniqueName = time() . '_' . Str::random(10) . '.' . $extension;
                $basePath = 'software/' . $software->id;

                $filePath = FileUploadController::storeFile($files, $basePath, $uniqueName);

                $originalFilename = $files->getClientOriginalName();
                $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);

                $attachment = SoftwareAttachment::create([
                    'software_id' => $software->id,
                    'file_path' => $filePath,
                    'original_filename' => $originalFilename,
                    'display_name' => $displayName,
                    'file_extension' => $extension,
                    'mime_type' => $files->getMimeType(),
                    'file_size' => $files->getSize(),
                    'uploaded_by' => $authUser->id,
                ]);

                $uploadedAttachments[] = $attachment;
                $count++;
            }
        }

        // Log audit (uno solo per l'operazione multipla)
        if ($count > 0) {
            SoftwareAuditLog::create([
                'log_subject' => 'software_attachment',
                'log_type' => 'create',
                'modified_by' => $authUser->id,
                'software_id' => $software->id,
                'old_data' => null,
                'new_data' => json_encode([
                    'files_count' => $count,
                    'attachment_ids' => array_map(fn($a) => $a->id, $uploadedAttachments),
                ]),
            ]);
        }

        return response([
            'attachments' => $uploadedAttachments,
            'filesCount' => $count,
            'message' => $count . ' file caricati con successo',
        ], 201);
    }

    /**
     * Update attachment display name
     */
    public function updateAttachment(Software $software, SoftwareAttachment $attachment, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono modificare
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $software->company_id !== $authUser->selectedCompany()?->id) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Verifica che l'allegato appartenga al software
        if ($attachment->software_id !== $software->id) {
            return response(['message' => 'Attachment does not belong to this software'], 400);
        }

        $fields = $request->validate([
            'display_name' => 'required|string|max:255',
        ]);

        $oldName = $attachment->display_name;
        $attachment->update(['display_name' => $fields['display_name']]);

        // Log audit
        SoftwareAuditLog::create([
            'log_subject' => 'software_attachment',
            'log_type' => 'update',
            'modified_by' => $authUser->id,
            'software_id' => $software->id,
            'old_data' => json_encode(['display_name' => $oldName]),
            'new_data' => json_encode(['display_name' => $fields['display_name']]),
        ]);

        return response([
            'attachment' => $attachment,
            'message' => 'Nome allegato aggiornato',
        ], 200);
    }

    /**
     * Delete attachment (soft delete)
     */
    public function deleteAttachment(Software $software, SoftwareAttachment $attachment, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono eliminare
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $software->company_id !== $authUser->selectedCompany()?->id) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Verifica che l'allegato appartenga al software
        if ($attachment->software_id !== $software->id) {
            return response(['message' => 'Attachment does not belong to this software'], 400);
        }

        $attachmentData = [
            'id' => $attachment->id,
            'filename' => $attachment->downloadFilename(),
        ];

        // Soft delete (il file rimane su GCS)
        $attachment->delete();

        // Log audit
        SoftwareAuditLog::create([
            'log_subject' => 'software_attachment',
            'log_type' => 'delete',
            'modified_by' => $authUser->id,
            'software_id' => $software->id,
            'old_data' => json_encode($attachmentData),
            'new_data' => null,
        ]);

        return response(['message' => 'Allegato eliminato'], 200);
    }

    /**
     * Restore soft deleted attachment (solo admin)
     */
    public function restoreAttachment(Software $software, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Solo admin può ripristinare
        if (!$authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Trova l'allegato soft deleted
        $attachment = SoftwareAttachment::withTrashed()
            ->where('id', $attachmentId)
            ->where('software_id', $software->id)
            ->first();

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        if (!$attachment->trashed()) {
            return response(['message' => 'Attachment is not deleted'], 400);
        }

        $attachment->restore();

        // Log audit
        SoftwareAuditLog::create([
            'log_subject' => 'software_attachment',
            'log_type' => 'restore',
            'modified_by' => $authUser->id,
            'software_id' => $software->id,
            'old_data' => null,
            'new_data' => json_encode([
                'attachment_id' => $attachment->id,
                'filename' => $attachment->downloadFilename(),
            ]),
        ]);

        return response([
            'attachment' => $attachment->load('uploader'),
            'message' => 'Allegato ripristinato',
        ], 200);
    }

    /**
     * Force delete attachment (solo admin - elimina definitivamente file da GCS)
     */
    public function forceDeleteAttachment(Software $software, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Solo admin può eliminare definitivamente
        if (!$authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Trova l'allegato soft deleted
        $attachment = SoftwareAttachment::withTrashed()
            ->where('id', $attachmentId)
            ->where('software_id', $software->id)
            ->first();

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        $attachmentData = [
            'id' => $attachment->id,
            'filename' => $attachment->downloadFilename(),
            'file_path' => $attachment->file_path,
        ];

        // Force delete (elimina file da GCS tramite boot del model)
        $attachment->forceDelete();

        // Log audit
        SoftwareAuditLog::create([
            'log_subject' => 'software_attachment',
            'log_type' => 'permanent_delete',
            'modified_by' => $authUser->id,
            'software_id' => $software->id,
            'old_data' => json_encode($attachmentData),
            'new_data' => null,
        ]);

        return response(['message' => 'Allegato eliminato definitivamente'], 200);
    }

    /**
     * Get download URL for attachment
     */
    public function getDownloadUrl(Software $software, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Verifica permessi
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        } elseif ($authUser->is_company_admin) {
            if ($software->company_id !== $authUser->selectedCompany()?->id) {
                return response(['message' => 'Unauthorized'], 403);
            }
        }

        // Admin può scaricare anche file soft deleted
        $attachment = $authUser->is_admin 
            ? SoftwareAttachment::withTrashed()->where('id', $attachmentId)->first()
            : SoftwareAttachment::find($attachmentId);

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Verifica che l'allegato appartenga al software
        if ($attachment->software_id !== $software->id) {
            return response(['message' => 'Attachment does not belong to this software'], 400);
        }

        $url = $attachment->getDownloadUrl();

        return response([
            'url' => $url,
            'filename' => $attachment->downloadFilename(),
        ], 200);
    }

    /**
     * Get preview URL for attachment
     */
    public function getPreviewUrl(Software $software, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Verifica permessi
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        } elseif ($authUser->is_company_admin) {
            if ($software->company_id !== $authUser->selectedCompany()?->id) {
                return response(['message' => 'Unauthorized'], 403);
            }
        }

        // Admin può vedere preview anche di file soft deleted
        $attachment = $authUser->is_admin 
            ? SoftwareAttachment::withTrashed()->where('id', $attachmentId)->first()
            : SoftwareAttachment::find($attachmentId);

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Verifica che l'allegato appartenga al software
        if ($attachment->software_id !== $software->id) {
            return response(['message' => 'Attachment does not belong to this software'], 400);
        }

        $url = $attachment->getPreviewUrl();

        return response([
            'url' => $url,
            'filename' => $attachment->downloadFilename(),
            'mime_type' => $attachment->mime_type,
            'is_image' => $attachment->isImage(),
            'is_pdf' => $attachment->isPdf(),
        ], 200);
    }
}

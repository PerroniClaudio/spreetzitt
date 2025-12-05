<?php

namespace App\Http\Controllers;

use App\Exports\HardwareAssignationTemplateExport;
use App\Exports\HardwareDeletionTemplateExport;
use App\Exports\HardwareExport;
use App\Exports\HardwareLogsExport;
use App\Exports\HardwareTemplateExport;
use App\Imports\HardwareAssignationsImport;
use App\Imports\HardwareDeletionsImport;
use App\Imports\HardwareImport;
use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareAttachment;
use App\Models\HardwareAuditLog;
use App\Models\HardwareType;
use App\Models\TypeFormFields;
use App\Models\User;
use App\Http\Controllers\FileUploadController;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

use function PHPUnit\Framework\isEmpty;

class HardwareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        if ($authUser->is_admin) {
            $hardwareList = Hardware::with([
                'hardwareType',
                'company',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                }
            ])->get();

            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }

        if ($authUser->is_company_admin) {
            $selectedCompany = $authUser->selectedCompany();
            $hardwareList = $selectedCompany 
                ? Hardware::where('company_id', $selectedCompany->id)->with([
                    'hardwareType',
                    'company',
                    'users' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                    }
                ])->get() 
                : collect();

            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }

        $selectedCompany = $authUser->selectedCompany();
        $hardwareList = $selectedCompany ? Hardware::where('company_id', $selectedCompany->id)->whereHas('users', function ($query) use ($authUser) {
            $query->where('user_id', $authUser->id);
        })->with([
            'hardwareType',
            'company',
            'users' => function ($query) {
                $query->select('users.id', 'users.name', 'users.surname', 'users.email');
            }
        ])->get() : collect();

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function companyHardwareList(Request $request, Company $company)
    {
        $authUser = $request->user();
        if (! $authUser->is_admin && ! ($authUser->is_company_admin && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        $hardwareQuery = Hardware::query();

        if(!!$authUser->is_admin) {
            $hardwareQuery->withTrashed();
        }

        $hardwareList = $hardwareQuery->where('company_id', $company->id)
            ->with([
                'hardwareType', 
                'company',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                }
            ])
            ->get()
            // ->map(function ($hardware) {
            //     return [
            //         ...$hardware->toArray(),
            //         'users' => $hardware->users->pluck('id')->toArray(),
            //     ];
            // });
                ;
        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function userCompaniesHardwareList(Request $request, User $user) {
        $authUser = $request->user();
        if (
            !$authUser->is_admin
        ) {
            return response([
                'message' => 'You are not allowed to view this hardware list',
            ], 403);
        }

        $userCompanyIds = $user->companies()->pluck('companies.id')->toArray();
        $hardwareList = Hardware::whereIn('company_id', $userCompanyIds)
            ->with(['hardwareType:id,name', 'company:id,name'])
            ->get()
            ->map(function ($hardware) {
            $hardware->users = $hardware->users()->pluck('user_id')->toArray();
            return $hardware;
            });
        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function formFieldHardwareList(Request $request, TypeFormFields $typeFormField) {
        $authUser = $request->user();

        if (! $typeFormField) {
            return response([
                'message' => 'Type form field not found',
            ], 404);
        }

        $company = $typeFormField->ticketType->company;
        if (! $authUser->is_admin && ! ((bool) $company && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        $hardwareList = [];

        // Con una query unica
        // Costruisci la query di base
        if ($authUser->is_admin || $authUser->is_company_admin) {
            $query = Hardware::where('company_id', $company->id);
        } else {
            $query = $authUser->hardware();
        }
        // Aggiungi le relazioni
        $query->with([
            'hardwareType', 
            'company',
            'users' => function ($query) {
                $query->select('users.id', 'users.name', 'users.surname', 'users.email');
            }
        ]);
        // Se necessario rimuove gli hardware che non hanno il tipo associato
        if (! $typeFormField->include_no_type_hardware) {
            $query->whereNotNull('hardware_type_id');
        }
        if ($typeFormField->hardware_accessory_include === 'only_accessories') {
            $query->where('is_accessory', true);
        } elseif ($typeFormField->hardware_accessory_include === 'no_accessories') {
            $query->where('is_accessory', false);
        }
        // Se necessario limitare a determinati tipi di hardware (tenendo conto dell'hardware che non ha un tipo associato)
        if ($typeFormField->hardwareTypes->count() > 0) {
            $hardwareTypeIds = $typeFormField->hardwareTypes->pluck('id')->toArray();
            $query->where(function ($query) use ($hardwareTypeIds, $typeFormField) {
                $query->whereIn('hardware_type_id', $hardwareTypeIds);
                if ($typeFormField->include_no_type_hardware) {
                    $query->orWhereNull('hardware_type_id');
                }
            });
        }

        // Esegui la query
        $hardwareList = $query->get();

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function hardwareListWithTrashed(Request $request)
    {

        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        $hardwareList = Hardware::withTrashed()->with(['hardwareType', 'company'])->get();

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response([
            'message' => 'Please use /api/store to create a new hardware',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to create hardware',
            ], 403);
        }

        $allowedStatuses = array_keys(config('app.hardware_statuses'));
        $allowedPositions = array_keys(config('app.hardware_positions'));
        $allowedStatusesAtPurchase = array_keys(config('app.hardware_statuses_at_purchase'));

        $data = $request->validate([
            'make' => 'required|string',
            'model' => 'required|string',
            'serial_number' => 'required_unless:is_accessory,1|nullable|string',
            'is_accessory' => 'sometimes|boolean',
            'is_exclusive_use' => 'required|boolean',
            'status_at_purchase' => 'required|string|in:'.implode(',', $allowedStatusesAtPurchase),
            'status' => 'required|string|in:'.implode(',', $allowedStatuses),
            'position' => 'required|string|in:'.implode(',', $allowedPositions),
            'company_asset_number' => 'nullable|string',
            'support_label' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'company_id' => 'nullable|int',
            'hardware_type_id' => 'nullable|int',
            'ownership_type' => 'nullable|string',
            'ownership_type_note' => 'nullable|string',
            'notes' => 'nullable|string',
            'users' => 'nullable|array',
        ]);

        // Se non è un accessorio, controlla che almeno uno tra company_asset_number e support_label sia impostato
        $isAccessory = isset($data['is_accessory']) ? (bool) $data['is_accessory'] : false;
        if (! $isAccessory && empty($data['company_asset_number']) && empty($data['support_label'])) {
            return response([
                'message' => 'Deve essere specificato almeno uno tra company_asset_number e support_label.',
            ], 422);
        }

        if (isset($data['company_id']) && ! Company::find($data['company_id'])) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        // Aggiungere le associazioni utenti
        if (isset($data['company_id']) && ! empty($data['users'])) {
            // $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $data['company_id'])->exists();
            $isFail = User::whereIn('id', $data['users'])
                ->whereDoesntHave('companies', function ($query) use ($data) {
                    $query->where('companies.id', $data['company_id']);
                })
                ->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more users do not belong to the specified company',
                ], 400);
            }
        }

        $hardware = Hardware::create($data);

        if ($hardware->company_id) {
            HardwareAuditLog::create([
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'log_subject' => 'hardware_company',
                'log_type' => 'create',
                'new_data' => json_encode(['company_id' => $hardware->company_id]),
            ]);
        }

        if (! empty($data['users'])) {
            // Non so perchè ma non crea i log in automatico, quindi devo aggiungerli manualmente
            // $hardware->users()->attach($data['users']);

            foreach ($data['users'] as $userId) {
                $hardware->users()->attach($userId, [
                    'created_by' => $authUser->id,
                    'responsible_user_id' => $authUser->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        return response([
            'hardware' => $hardware,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $hardwareId)
    {
        $authUser = $request->user();
        $hardware = null;

        if ($authUser->is_admin) {
            $hardware = Hardware::withTrashed()->find($hardwareId);
        } else {
            $hardware = Hardware::find($hardwareId);
        }

        if (! $hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }

        if (
            ! $authUser->is_admin
            && ! ($authUser->is_company_admin && $authUser->companies()->where('companies.id', $hardware->company_id)->exists())
            && ! (in_array($authUser->id, $hardware->users->pluck('id')->toArray()))
        ) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        if ($authUser->is_admin || $authUser->is_company_admin) {
            // $hardware->load(['company', 'hardwareType', 'users']);
            // if (!$hardware) {
            //     $hardware = Hardware::withTrashed()->find($hardware->id);
            // }
            $hardware->load([
                'company' => function ($query) {
                    $query->select('id', 'name');
                },
                'hardwareType',
                'users' => function ($query) {
                    $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted'); // Limit user data sent to frontend
                },
            ]);
        } else {
            $hardware->load([
                'company' => function ($query) {
                    $query->select('id', 'name');
                },
                'hardwareType',
                'users' => function ($query) {
                    $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted'); // Limit user data sent to frontend
                },
            ]);
        }

        return response([
            'hardware' => $hardware,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Hardware $hardware)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Hardware $hardware)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to edit hardware',
            ], 403);
        }

        $allowedStatusesAtPurchase = array_keys(config('app.hardware_statuses_at_purchase'));
        $allowedStatuses = array_keys(config('app.hardware_statuses'));
        $allowedPositions = array_keys(config('app.hardware_positions'));

        $data = $request->validate([
            'make' => 'required|string',
            'model' => 'required|string',
            'serial_number' => 'required_unless:is_accessory,1|nullable|string',
            'is_accessory' => 'sometimes|boolean',
            'is_exclusive_use' => 'required|boolean',
            'status_at_purchase' => 'required|string|in:'.implode(',', $allowedStatusesAtPurchase),
            'status' => 'required|string|in:'.implode(',', $allowedStatuses),
            'position' => 'required|string|in:'.implode(',', $allowedPositions),
            'company_asset_number' => 'nullable|string',
            'support_label' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'company_id' => 'nullable|int',
            'hardware_type_id' => 'nullable|int',
            'ownership_type' => 'nullable|string',
            'ownership_type_note' => 'nullable|string',
            'notes' => 'nullable|string',
            'users' => 'nullable|array',
        ]);

        if (isset($data['company_id']) && ! Company::find($data['company_id'])) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        // Se non è un accessorio, controlla che almeno uno tra company_asset_number e support_label sia impostato
        $isAccessory = isset($data['is_accessory']) ? (bool) $data['is_accessory'] : $hardware->is_accessory;
        if (! $isAccessory && empty($data['company_asset_number']) && empty($data['support_label'])) {
            return response([
                'message' => 'Deve essere specificato almeno uno tra company_asset_number e support_label.',
            ], 422);
        }

        // controllare le associazioni utenti
        if (isset($data['company_id']) && ! empty($data['users'])) {
            // $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $data['company_id'])->exists();
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

        if (! $hardware->is_exclusive_use && $data['is_exclusive_use'] && count($data['users']) > 1) {
            return response([
                'message' => 'Exclusive use hardware can be associated to no more than one user. Hardware not updated.',
            ], 400);
        }

        $oldCompanyId = $hardware->company_id;

        // Aggiorna l'hardware
        $hardware->update($data);

        if ($hardware->company_id != $oldCompanyId) {
            $logType = $oldCompanyId ? ($hardware->company_id ? 'update' : 'delete') : 'create';
            $oldData = $oldCompanyId ? json_encode(['company_id' => $oldCompanyId]) : null;
            $newData = $hardware->company_id ? json_encode(['company_id' => $hardware->company_id]) : null;
            HardwareAuditLog::create([
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'log_subject' => 'hardware_company',
                'log_type' => $logType,
                'old_data' => $oldData,
                'new_data' => $newData,
            ]);
        }

        // Aggiorna gli utenti associati
        // Non so perchè ma non crea i log in automatico, quindi devo aggiungerli manualmente
        // $hardware->users()->attach($data['users']);

        $usersToRemove = $hardware->users->pluck('id')->diff($data['users']);
        $usersToAdd = collect($data['users'])->diff($hardware->users->pluck('id'));

        foreach ($usersToAdd as $userId) {
            $hardware->users()->attach($userId, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $hardware->users()->detach($userId);
        }

        return response([
            'hardware' => $hardware,
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($hardwareId, Request $request)
    {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1);

        $user = $request->user();
        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        $hardware = Hardware::findOrFail($hardwareId);
        if (! $hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        $hardware->delete();
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'hardware_id' => null,
            'log_subject' => 'hardware',
            'log_type' => 'delete',
            'old_data' => json_encode($hardware->toArray()),
            'new_data' => null,
        ]);

        return response([
            'message' => 'Hardware soft deleted successfully',
        ], 200);
    }

    public function destroyTrashed($hardwareId, Request $request)
    {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1);

        $user = $request->user();
        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        $hardware = Hardware::withTrashed()->findOrFail($hardwareId);
        if (! $hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        $hardware->forceDelete();
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'hardware_id' => null,
            'log_subject' => 'hardware',
            'log_type' => 'permanent-delete',
            'old_data' => json_encode($hardware->toArray()),
            'new_data' => null,
        ]);

        return response([
            'message' => 'Hardware deleted successfully',
        ], 200);
    }

    public function restore($hardwareId, Request $request)
    {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1);

        $user = $request->user();
        if (! $user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        $hardware = Hardware::withTrashed()->findOrFail($hardwareId);
        if (! $hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        $hardware->restore();
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'hardware_id' => $hardwareId,
            'log_subject' => 'hardware',
            'log_type' => 'restore',
            'old_data' => null,
            'new_data' => json_encode($hardware->toArray()),
        ]);

        return response([
            'message' => 'Hardware restored successfully',
        ], 200);
    }

    /**
     * Update the assigned users of the single hardware
     */
    public function updateHardwareUsers(Request $request, Hardware $hardware)
    {
        $hardware = Hardware::find($hardware->id);
        if (! $hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }

        $authUser = $request->user();
        if (! ($authUser->is_company_admin && $authUser->companies()->where('companies.id', $hardware->company_id)->exists()) && ! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update hardware users',
            ], 403);
        }

        $data = $request->validate([
            'users' => 'nullable|array',
        ]);

        $company = $hardware->company;

        if (! empty($data['users']) && ! $company) {
            return response([
                'message' => 'Hardware must be associated with a company to add users',
            ], 404);
        }

        if ($company && ! empty($data['users'])) {
            // $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $company->id)->exists();
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

        $users = User::whereIn('id', $data['users'])->get();
        if ($users->count() != count($data['users'])) {
            return response([
                'message' => 'One or more users not found',
            ], 404);
        }

        $usersToRemove = $hardware->users->pluck('id')->diff($data['users']);
        $usersToAdd = collect($data['users'])->diff($hardware->users->pluck('id'));

        // Solo l'admin può rimuovere associazioni hardware-user
        if (! $authUser->is_admin && count($usersToRemove) > 0) {
            return response([
                'message' => 'You are not allowed to remove users from hardware',
            ], 403);
        }

        // L'hardware ad uso sclusivo può essere associato a un solo utente
        if (
            $hardware->is_exclusive_use &&
            (count($usersToAdd) > 0 &&
                // Qui forse basterebbe $request->users->count() > 1
                (($hardware->users->count() - count($usersToRemove) + count($usersToAdd)) > 1)
            )
        ) {
            return response([
                'message' => 'This hardware can be associated to only one user.',
            ], 400);
        }

        foreach ($usersToAdd as $userId) {
            $hardware->users()->attach($userId, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $hardware->users()->detach($userId);
        }

        return response([
            'message' => 'Hardware users updated successfully',
        ], 200);
    }

    /**
     * Update the assigned hardware of the single user
     */
    public function updateUserHardware(Request $request, User $user)
    {
        $user = User::find($user->id);
        if (! $user) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }

        $authUser = $request->user();
        if (
            ! $authUser->is_admin &&
            ! (
                $authUser->is_company_admin &&
                $user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists()
            )
        ) {
            return response([
                'message' => 'You are not allowed to update hardware users',
            ], 403);
        }

        $data = $request->validate([
            'hardware' => 'nullable|array',
        ]);

        $userHasAtLeastOneCompany = $user->companies()->exists();

        if (! empty($data['hardware']) && ! $userHasAtLeastOneCompany) {
            return response([
                'message' => 'User must be associated with a company to add hardware',
            ], 404);
        }

        if ($userHasAtLeastOneCompany && ! empty($data['hardware'])) {
            // $isFail = Hardware::whereIn('id', $data['hardware'])->where('company_id', '!=', $company->id)->exists();
            $isFail = Hardware::whereIn('id', $data['hardware'])
                ->whereNotIn('company_id', $user->companies()->pluck('companies.id'))
                ->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected hardware do not belong to the user\'s company',
                ], 400);
            }
        }

        $hardware = Hardware::whereIn('id', $data['hardware'])->get();
        if ($hardware->count() != count($data['hardware'])) {
            return response([
                'message' => 'One or more hardware not found',
            ], 404);
        }

        // Se è admin hardware to remove va preso tutto, altrimenti dovrebbe essere filtrato con selectedCompany()
        if ($authUser->is_admin) {
            $hardwareToRemove = $user->hardware->pluck('id')->diff($data['hardware']);
        } else {
            $hardwareToRemove = $user->hardware()->where('company_id', $authUser->selectedCompany()->id)->pluck('id')->diff($data['hardware']);
        }

        $hardwareToAdd = collect($data['hardware'])->diff($user->hardware->pluck('id'));

        // Solo l'admin può rimuovere associazioni hardware-user
        if (! $authUser->is_admin && count($hardwareToRemove) > 0) {
            return response([
                'message' => 'You are not allowed to remove hardware from user',
            ], 403);
        }

        if (count($hardwareToAdd) > 0) {
            foreach ($hardwareToAdd as $hardwareId) {
                $hwToAdd = Hardware::find($hardwareId);
                if ($hwToAdd->is_exclusive_use && ($hwToAdd->users->count() >= 1)) {
                    return response([
                        'message' => 'A selected hardware ('.$hwToAdd->id.') can only be associated to one user and has already been associated.',
                    ], 400);
                }
            }
        }

        foreach ($hardwareToAdd as $hardwareId) {
            $user->hardware()->attach($hardwareId, [
                'created_by' => $authUser->id,
                'responsible_user_id' => $authUser->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($hardwareToRemove as $hardwareId) {
            $user->hardware()->detach($hardwareId);
        }

        return response([
            'message' => 'User assigned hardware updated successfully',
        ], 200);
    }

    public function deleteHardwareUser($hardwareId, $userId, Request $request)
    {
        $hardware = Hardware::findOrFail($hardwareId);
        $user = User::findOrFail($userId);

        if (! $hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        if (! $user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $authUser = $request->user();
        // Adesso può farlo solo l'admin
        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware-user associations.',
            ], 403);
        }

        if (! $hardware->users->contains($user)) {
            return response([
                'message' => 'User not associated with hardware',
            ], 400);
        }

        $hardware->users()->detach($userId);

        return response()->json(['message' => 'User detached from hardware successfully'], 200);
    }

    public function userHardwareList(Request $request, User $user)
    {
        $authUser = $request->user();
        if (! $authUser->is_admin
            // && !($user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists() && $authUser->is_company_admin)
            && ! $user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists() //per ora non è limitato al company_admin
            && ! ($authUser->id == $user->id)
        ) {
            return response([
                'message' => 'You are not allowed to view this user hardware',
            ], 403);
        }

        // lato admin si vede tutto e lato utente si deve vedere solo quello della sua azienda
        if ($authUser->is_admin) {
            $hardwareList = $user->hardware()->with([
                'hardwareType',
                'company',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                }
            ])->get();
        } else {
            $hardwareList = $user->hardware()
                ->where('company_id', $authUser->selectedCompany()->id)
                ->with([
                    'hardwareType',
                    'company',
                    'users' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.surname', 'users.email');
                    }
                ])
                ->get();
        }

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function fakeHardwareField(Request $request)
    {
        // Dati fittizi statici per test
        $fakeCompany = (object) [
            'id' => 1,
            'name' => 'TestCompany',
        ];

        // Genera dati fittizi per HardwareType
        $fakeHardwareTypes = collect([
            (object) ['id' => 1, 'name' => 'Laptop'],
            (object) ['id' => 2, 'name' => 'Desktop'],
            (object) ['id' => 3, 'name' => 'Server'],
            (object) ['id' => 4, 'name' => 'Network'],
            (object) ['id' => 5, 'name' => 'Printer'],
        ]);

        // Genera dati fittizi per Hardware
        $fakeHardwareList = collect([
            [
                'id' => 1,
                'make' => 'Dell',
                'model' => 'Latitude',
                'serial_number' => 'TEST-001',
                'company_id' => 1,
                'hardware_type_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hardwareType' => ['id' => 1, 'name' => 'Laptop'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 2,
                'make' => 'HP',
                'model' => 'EliteBook',
                'serial_number' => 'TEST-002',
                'company_id' => 1,
                'hardware_type_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hardwareType' => ['id' => 1, 'name' => 'Laptop'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 3,
                'make' => 'Lenovo',
                'model' => 'ThinkPad',
                'serial_number' => 'TEST-003',
                'company_id' => 1,
                'hardware_type_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hardwareType' => ['id' => 1, 'name' => 'Laptop'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 4,
                'make' => 'Apple',
                'model' => 'MacBook',
                'serial_number' => 'TEST-004',
                'company_id' => 1,
                'hardware_type_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hardwareType' => ['id' => 1, 'name' => 'Laptop'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
            [
                'id' => 5,
                'make' => 'Asus',
                'model' => 'VivoBook',
                'serial_number' => 'TEST-005',
                'company_id' => 1,
                'hardware_type_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hardwareType' => ['id' => 1, 'name' => 'Laptop'],
                'company' => ['id' => 1, 'name' => 'TestCompany'],
            ],
        ]);

        return response([
            'company' => $fakeCompany,
            'hardwareTypes' => $fakeHardwareTypes,
            'hardware' => $fakeHardwareList,
        ], 200);
    }

    public function hardwareTickets(Request $request, Hardware $hardware)
    {
        $authUser = $request->user();
        if (
            ! $authUser->is_admin
            && ! ($authUser->is_company_admin && $authUser->companies()->where('companies.id', $hardware->company_id)->exists())
            && ! ($hardware->users->contains($authUser))
        ) {
            return response([
                'message' => 'You are not allowed to view this hardware tickets',
            ], 403);
        }

        if ($authUser->is_admin) {
            $tickets = $hardware->tickets()->with([
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

        // Non sappiamo se l'hardware può passare da un'azienda all'altra.
        if ($authUser->is_company_admin) {
            $tickets = $hardware->tickets()->where('company_id', $hardware->company_id)->with([
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

        // Qui devono vedersi tutti i ticket collegati a questo hardware, aperti dall'utente o in cui è associato come utente interessato (referer)
        if ($hardware->users->contains($authUser)) {
            $tickets = $hardware->tickets()
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
            'message' => 'You are not allowed to view this hardware tickets',
        ], 403);
    }

    public function exportTemplate()
    {
        $name = 'hardware_import_template_'.time().'.xlsx';

        return Excel::download(new HardwareTemplateExport, $name);
    }

    public function exportAssignationTemplate()
    {
        $name = 'hardware_assignation_template_'.time().'.xlsx';

        return Excel::download(new HardwareAssignationTemplateExport, $name);
    }

    public function exportDeletionTemplate()
    {
        $name = 'hardware_assignation_template_'.time().'.xlsx';

        return Excel::download(new HardwareDeletionTemplateExport, $name);
    }

    public function importHardware(Request $request)
    {

        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import hardware',
            ], 403);
        }

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

            try {
                Excel::import(new HardwareImport($authUser), $file, 'xlsx');
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

    public function importHardwareAssignations(Request $request)
    {

        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import hardware assignations',
            ], 403);
        }

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

            try {
                Excel::import(new HardwareAssignationsImport($authUser), $file, 'xlsx');
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

    public function importHardwareDeletions(Request $request)
    {

        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import hardware deletions',
            ], 403);
        }

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

            try {
                Excel::import(new HardwareDeletionsImport($authUser), $file, 'xlsx');
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

    public function downloadUserAssignmentPdf(Hardware $hardware, User $user, Request $request)
    {
        $authUser = $request->user();
        if (! $authUser->is_admin
            && ! ($authUser->is_company_admin
                && (isset($hardware->company_id) && $hardware->company_id == ($authUser->selectedCompany()->id ?? null))
            )
        ) {
            return response([
                'message' => 'You are not allowed to download this document',
            ], 403);
        }

        if (! $hardware->users->contains($user)) {
            return response([
                'message' => 'User not associated with hardware',
            ], 400);
        }

        // $fileName = 'hardware_assignment_' . $hardware->id . '_to_' . $user->id . '.pdf';

        $hardwareFileName = $hardware->support_label
            ?? $hardware->company_asset_number
            ?? $hardware->serial_number
            ?? $hardware->model
            ?? $hardware->id;
        $userFileName = $user->surname
            ? ($user->name ? $user->surname.'_'.$user->name : $user->surname)
            : ($user->name ?? $user->id);
        $name = 'hardware_user_assignment_'.$hardwareFileName.'_to_'.$userFileName.'_'.time().'.pdf';
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);

        $hardware->load(['hardwareType', 'company']);

        $relation = $hardware->users()->wherePivot('user_id', $user->id)->first();

        // Gestione logo per sviluppo e produzione
        $google_url = null;
        if ($hardware->company) {
            $brand = $hardware->company->brands()->first();
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
            'hardware' => $hardware,
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

        $pdf = Pdf::loadView('pdf.hardwareuserassignment', $data);

        // return $pdf->stream();
        return $pdf->download($name);
    }

    public function getHardwareLog($hardwareId, Request $request)
    {
        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this hardware log',
            ], 403);
        }

        $logs = HardwareAuditLog::where('hardware_id', $hardwareId)->orWhere(function ($query) use ($hardwareId) {
            $query->whereJsonContains('old_data->id', $hardwareId)
                ->orWhereJsonContains('new_data->id', $hardwareId);
        })
            ->with('author')
            ->get();

        return response([
            'logs' => $logs,
        ], 200);
    }

    public function hardwareLogsExport($hardwareId, Request $request)
    {
        $authUser = $request->user();
        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to export this hardware log',
            ], 403);
        }

        $name = 'hardware_'.$hardwareId.'_logs_'.time().'.xlsx';

        return Excel::download(new HardwareLogsExport($hardwareId), $name);
    }

    /**
     * Export all hardware (admin only, can include trashed)
     */
    public function exportAllHardware(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to export all hardware',
            ], 403);
        }

        $includeTrashed = true;
        $name = 'all_hardware_export_' . time() . '.xlsx';

        try {
            // Aumenta temporaneamente il limite di memoria
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);
            
            return Excel::download(new HardwareExport(null, null, $includeTrashed), $name);
        } catch (\Exception $e) {
            Log::error('Hardware export failed: ' . $e->getMessage(), [
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
     * Export company hardware
     */
    public function exportCompanyHardware(Request $request, Company $company)
    {
        $authUser = $request->user();
        
        if (!$authUser->is_admin && !($authUser->is_company_admin && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to export this company\'s hardware',
            ], 403);
        }

        // Solo gli admin possono includere il trashed
        $includeTrashed = $authUser->is_admin;
        $name = 'company_' . $company->name . '_hardware_export_' . time() . '.xlsx';

        return Excel::download(new HardwareExport($company->id, null, $includeTrashed), $name);
    }

    /**
     * Export user hardware
     */
    public function exportUserHardware(Request $request, User $user)
    {
        $authUser = $request->user();
        
        // Controllo autorizzazioni
        if (!$authUser->is_admin 
            && !($authUser->is_company_admin && $user->companies()->whereIn('companies.id', $authUser->companies()->pluck('companies.id'))->exists())
            && !($authUser->id == $user->id)
        ) {
            return response([
                'message' => 'You are not allowed to export this user\'s hardware',
            ], 403);
        }

        // Solo gli admin possono includere il trashed
        // $includeTrashed = $authUser->is_admin && $request->boolean('include_trashed', false);
        $includeTrashed = $authUser->is_admin;
        
        $userFileName = $user->surname 
            ? ($user->name ? $user->surname . '_' . $user->name : $user->surname)
            : ($user->name ?? $user->id);
        $name = 'user_' . $userFileName . '_hardware_export_' . time() . '.xlsx';
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
        

        return Excel::download(new HardwareExport($authUser->is_admin ? null : $authUser->selectedCompany()?->id, $user->id, $includeTrashed), $name);
    }

    /**
     * Get all attachments for hardware
     */
    public function getAttachments(Hardware $hardware, Request $request)
    {
        $authUser = $request->user();

        // Verifica permessi
        if (!$authUser->is_admin) {
            if ($authUser->is_company_admin) {
                // Company admin: può vedere solo hardware della sua azienda
                if ($hardware->company_id !== $authUser->selectedCompany()?->id) {
                    return response(['message' => 'Unauthorized'], 403);
                }
            } else {
                // User normale: può vedere solo se l'hardware è assegnato a lui
                if (!$hardware->users()->where('user_id', $authUser->id)->exists()) {
                    return response(['message' => 'Unauthorized'], 403);
                }
            }
        }

        // Admin vede tutti gli allegati (anche soft deleted)
        // User e Company Admin vedono solo quelli non eliminati
        if ($authUser->is_admin) {
            $attachments = $hardware->attachments()->withTrashed()->with('uploader')->get();
        } else {
            $attachments = $hardware->attachments()->with('uploader')->get();
        }

        return response(['attachments' => $attachments], 200);
    }

    /**
     * Upload attachment for hardware
     */
    public function uploadAttachment(Hardware $hardware, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono caricare allegati
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $hardware->company_id !== $authUser->selectedCompany()?->id) {
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
        $path = 'hardware/' . $hardware->id;

        // Upload usando FileUploadController
        $filePath = FileUploadController::storeFile($file, $path, $uniqueName);

        // Crea record nel database
        $attachment = HardwareAttachment::create([
            'hardware_id' => $hardware->id,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'display_name' => $fields['display_name'] ?? null,
            'file_extension' => $extension,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $authUser->id,
        ]);

        // Log audit
        HardwareAuditLog::create([
            'log_subject' => 'hardware_attachment',
            'log_type' => 'create',
            'modified_by' => $authUser->id,
            'hardware_id' => $hardware->id,
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
     * Upload multiple attachments for hardware
     */
    public function uploadAttachments(Hardware $hardware, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono caricare allegati
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $hardware->company_id !== $authUser->selectedCompany()?->id) {
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
                    $basePath = 'hardware/' . $hardware->id;

                    // Upload usando FileUploadController
                    $filePath = FileUploadController::storeFile($file, $basePath, $uniqueName);

                    // Crea record nel database
                    // display_name viene dal nome originale del file (senza estensione)
                    $originalFilename = $file->getClientOriginalName();
                    $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);

                    $attachment = HardwareAttachment::create([
                        'hardware_id' => $hardware->id,
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
                $basePath = 'hardware/' . $hardware->id;

                $filePath = FileUploadController::storeFile($files, $basePath, $uniqueName);

                $originalFilename = $files->getClientOriginalName();
                $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);

                $attachment = HardwareAttachment::create([
                    'hardware_id' => $hardware->id,
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
            HardwareAuditLog::create([
                'log_subject' => 'hardware_attachment',
                'log_type' => 'create',
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
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
    public function updateAttachment(Hardware $hardware, HardwareAttachment $attachment, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono modificare
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $hardware->company_id !== $authUser->selectedCompany()?->id) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Verifica che l'allegato appartenga all'hardware
        if ($attachment->hardware_id !== $hardware->id) {
            return response(['message' => 'Attachment does not belong to this hardware'], 400);
        }

        $fields = $request->validate([
            'display_name' => 'required|string|max:255',
        ]);

        $oldName = $attachment->display_name;
        $attachment->update(['display_name' => $fields['display_name']]);

        // Log audit
        HardwareAuditLog::create([
            'log_subject' => 'hardware_attachment',
            'log_type' => 'update',
            'modified_by' => $authUser->id,
            'hardware_id' => $hardware->id,
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
    public function deleteAttachment(Hardware $hardware, HardwareAttachment $attachment, Request $request)
    {
        $authUser = $request->user();

        // Solo admin e company admin possono eliminare
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        if ($authUser->is_company_admin && $hardware->company_id !== $authUser->selectedCompany()?->id) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Verifica che l'allegato appartenga all'hardware
        if ($attachment->hardware_id !== $hardware->id) {
            return response(['message' => 'Attachment does not belong to this hardware'], 400);
        }

        $attachmentData = [
            'id' => $attachment->id,
            'filename' => $attachment->downloadFilename(),
        ];

        // Soft delete (il file rimane su GCS)
        $attachment->delete();

        // Log audit
        HardwareAuditLog::create([
            'log_subject' => 'hardware_attachment',
            'log_type' => 'delete',
            'modified_by' => $authUser->id,
            'hardware_id' => $hardware->id,
            'old_data' => json_encode($attachmentData),
            'new_data' => null,
        ]);

        return response(['message' => 'Allegato eliminato'], 200);
    }

    /**
     * Restore soft deleted attachment (solo admin)
     */
    public function restoreAttachment(Hardware $hardware, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Solo admin può ripristinare
        if (!$authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Trova l'allegato soft deleted
        $attachment = HardwareAttachment::withTrashed()
            ->where('id', $attachmentId)
            ->where('hardware_id', $hardware->id)
            ->first();

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        if (!$attachment->trashed()) {
            return response(['message' => 'Attachment is not deleted'], 400);
        }

        $attachment->restore();

        // Log audit
        HardwareAuditLog::create([
            'log_subject' => 'hardware_attachment',
            'log_type' => 'restore',
            'modified_by' => $authUser->id,
            'hardware_id' => $hardware->id,
            'old_data' => null,
            'new_data' => json_encode([
                'id' => $attachment->id,
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
    public function forceDeleteAttachment(Hardware $hardware, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Solo admin può eliminare definitivamente
        if (!$authUser->is_admin) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Trova l'allegato soft deleted
        $attachment = HardwareAttachment::withTrashed()
            ->where('id', $attachmentId)
            ->where('hardware_id', $hardware->id)
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
        HardwareAuditLog::create([
            'log_subject' => 'hardware_attachment',
            'log_type' => 'permanent_delete',
            'modified_by' => $authUser->id,
            'hardware_id' => $hardware->id,
            'old_data' => json_encode($attachmentData),
            'new_data' => null,
        ]);

        return response(['message' => 'Allegato eliminato definitivamente'], 200);
    }

    /**
     * Get download URL for attachment
     */
    public function getDownloadUrl(Hardware $hardware, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Verifica permessi
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            if (!$hardware->users()->where('user_id', $authUser->id)->exists()) {
                return response(['message' => 'Unauthorized'], 403);
            }
        } elseif ($authUser->is_company_admin) {
            if ($hardware->company_id !== $authUser->selectedCompany()?->id) {
                return response(['message' => 'Unauthorized'], 403);
            }
        }

        // Admin può scaricare anche file soft deleted
        $attachment = $authUser->is_admin 
            ? HardwareAttachment::withTrashed()->find($attachmentId)
            : HardwareAttachment::find($attachmentId);

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Verifica che l'allegato appartenga all'hardware
        if ($attachment->hardware_id !== $hardware->id) {
            return response(['message' => 'Attachment does not belong to this hardware'], 400);
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
    public function getPreviewUrl(Hardware $hardware, $attachmentId, Request $request)
    {
        $authUser = $request->user();

        // Verifica permessi
        if (!$authUser->is_admin && !$authUser->is_company_admin) {
            if (!$hardware->users()->where('user_id', $authUser->id)->exists()) {
                return response(['message' => 'Unauthorized'], 403);
            }
        } elseif ($authUser->is_company_admin) {
            if ($hardware->company_id !== $authUser->selectedCompany()?->id) {
                return response(['message' => 'Unauthorized'], 403);
            }
        }

        // Admin può vedere preview anche di file soft deleted
        $attachment = $authUser->is_admin 
            ? HardwareAttachment::withTrashed()->find($attachmentId)
            : HardwareAttachment::find($attachmentId);

        if (!$attachment) {
            return response(['message' => 'Attachment not found'], 404);
        }

        // Verifica che l'allegato appartenga all'hardware
        if ($attachment->hardware_id !== $hardware->id) {
            return response(['message' => 'Attachment does not belong to this hardware'], 400);
        }

        $url = $attachment->getPreviewUrl();

        return response([
            'url' => $url,
            'filename' => $attachment->downloadFilename(),
            'is_image' => $attachment->isImage(),
            'is_pdf' => $attachment->isPdf(),
        ], 200);
    }
}

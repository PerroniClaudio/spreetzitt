<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TypeFormFields;
use App\Models\TicketTypeCategory;
use Illuminate\Http\Request;

class TicketTypeController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {

        // Si può decidere di non filtrarli prima, nel caso si dovessero vedere in qualche caso nel frontend.
        $ticketTypes = TicketType::where("is_deleted", false)->with('category')->get();
        // $ticketTypes = TicketType::with('category')->get();

        return response([
            'ticketTypes' => $ticketTypes,
        ], 200);
    }

    public function categories() {

        // $ticketTypeCategories = TicketTypeCategory::where("is_deleted", false)->get();
        $ticketTypeCategories = TicketTypeCategory::where("is_deleted", false)
            ->orderBy('name')
            ->orderBy('is_problem', 'desc')
            ->get();

        return response([
            'categories' => $ticketTypeCategories,
        ], 200);
    }

    public function updateCategory(Request $request, TicketTypeCategory $ticketTypeCategory) {

        $validated = $request->validate([
            'name' => 'required',
            'is_problem' => 'required',
            'is_request' => 'required',
        ]);

        $ticketTypeCategory->update($validated);

        return response([
            'ticketTypeCategory' => $ticketTypeCategory,
        ], 200);
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

        $validated = $request->validate([
            'name' => 'required',
            'ticket_type_category_id' => 'required',
            // 'company_id' => 'required|numeric',
            'default_priority' => 'required|string',
            'default_sla_solve' => 'required|numeric',
            'default_sla_take' => 'required|numeric',
            'is_master' => 'required|boolean',
            'is_scheduling' => 'required|boolean',
            'is_grouping' => 'required|boolean',
        ]);

        // $ticketType = TicketType::create($validated);

        $fillableFields = array_merge(
            $request->only((new TicketType)->getFillable())
        );
        $ticketType = TicketType::create($fillableFields);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    public function storeCategory(Request $request) {

        $validated = $request->validate([
            'name' => 'required',
            'is_problem' => 'required',
            'is_request' => 'required',
        ]);

        $ticketTypeCategory = TicketTypeCategory::create($validated);

        return response([
            'ticketTypeCategory' => $ticketTypeCategory,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketType $ticketType) {

        $ticketType = TicketType::where('id', $ticketType->id)->with('category')->first();

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketType $ticketType) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketType $ticketType) {

        $validated = $request->validate([
            'name' => 'required|string',
            'ticket_type_category_id' => 'required|numeric',
            'default_priority' => 'required|string',
            'default_sla_take' => 'required|numeric',
            'default_sla_solve' => 'required|numeric',
        ]);

        // $request['company_id'] = $request['company_id'] ? $request['company_id'] : null;
        // controllo ticket della compagnia precedente. se non ce ne sono si può modificare la compagnia, altrimenti no.
        if ($ticketType['company_id'] && $ticketType['company_id'] != $request['company_id'] && $ticketType->countRelatedTickets() > 0) {
            return response([
                'message' => 'Nessuna modifica effettuata. Non è possibile modificare ' . strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'l\'azienda')) . ' perché ci sono ticket associati con l\'attuale ' . strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'azienda')),
            ], 400);
        }
        // if ($ticketType->company_id != $request['company_id'] && $ticketType->countRelatedTickets()) {
        //     return response([
        //         'message' => 'Non è possibile modificare il tipo di ticket perché ci sono ticket associati con l\'attuale azienda',
        //     ], 400);
        // }

        $fillableFields = array_merge(
            $request->only((new TicketType)->getFillable())
        );

        // Se si vuole togliere it_referer_limited su un tipo master, verifica gli slave obbligatori
        if (
            isset($fillableFields['it_referer_limited']) && $fillableFields['it_referer_limited'] == 0 &&
            $ticketType->is_master == 1
        ) {
            $requiredSlaves = $ticketType->slaveTypes()->wherePivot('is_required', 1)->get();
            $hasLimitedSlave = $requiredSlaves->contains(function ($slave) {
                return $slave->it_referer_limited == 1;
            });
            if ($hasLimitedSlave) {
                // Per sicurezza, nel caso non fosse già a 1 lo si corregge.
                if($ticketType->it_referer_limited != 1) {
                    $ticketType->it_referer_limited = 1;
                    $ticketType->save();
                }
                return response([
                    'message' => 'Non puoi disattivare la limitazione ai referenti IT: uno dei tipi slave obbligatori è limitato ai referenti IT.'
                ], 400);
            }
        }

        $ticketType->update($fillableFields);

        $tt = TicketType::where('id', $ticketType->id)->with('category')->first();

        return response([
            'ticketType' =>  $tt,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketType $ticketType, Request $request) {
        $user = $request->user();
        if (!$user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $ticketType = TicketType::where('id', $ticketType["id"])->first();
        // Modificato quando l'azienda è stata resa facoltativa. se non ha l'azienda non dovrebbe avere nemmeno ticket allegati.
        // quindi si elimina direttamente, altrimenti countRelatedTickets dà errore, perchè passa dall'azienda.
        if ($ticketType->company && $ticketType->countRelatedTickets() > 0) {
            $ticketType->update([
                'is_deleted' => true,
            ]);
            return response([
                'message' => 'Ticket type deleted successfully',
            ], 200);
        } else {
            $deleted = TicketType::destroy($ticketType["id"]);
            if ($deleted) {
                return response(['message' => 'Ticket type deleted successfully'], 200);
            } else {
                return response(['message' => 'Ticket type not found'], 404);
            }
        }

        return response([
            'message' => 'Ticket type deleted successfully',
        ], 200);
    }

    public function getWebForm($id) {

        if ($id == 0) {
            return response([
                'webform' => [],
            ], 200);
        }

        $ticketType = TicketType::where('id', $id)->first();

        return response([
            'webform' => $ticketType->typeFormField,
            'slaves_with_webforms' => $ticketType->slaveTypes()->with(['category', 'typeFormField'])->get(),
        ], 200);
    }

    public function getGroups(TicketType $ticketType) {
        $groups = $ticketType->groups()->get();

        return response([
            'groups' => $groups,
        ], 200);
    }

    // public function getCompanies(TicketType $ticketType) {
    //     $companies = $ticketType->companies()->get();

    //     return response([
    //         'companies' => $companies,
    //     ], 200);
    // }

    public function getCompany(TicketType $ticketType) {
        $company = $ticketType->company()->get();

        return response([
            'company' => $company,
        ], 200);
    }

    public function updateCompanies(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'companies' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->companies()->sync($validated['companies']);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    // public function deleteCompany(Request $request) {

    //     $validated = $request->validate([
    //         'ticket_type_id' => 'required',
    //         'company_id' => 'required',
    //     ]);

    //     $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

    //     $ticketType->companies()->detach($validated['company_id']);

    //     $companies = $ticketType->companies()->get();

    //     return response([
    //         'companies' => $companies,
    //     ], 200);
    // }

    // public function updateSla(Request $request) {

    //     $validated = $request->validate([
    //         'ticket_type_id' => 'required',
    //         'company_id' => 'required',
    //         'sla_taking_charge' => 'required',
    //         'sla_resolving' => 'required',
    //     ]);

    //     $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

    //     $ticketType->companies()->updateExistingPivot(
    //         $validated['company_id'],
    //         [
    //             'sla_taking_charge' => $validated['sla_taking_charge'],
    //             'sla_resolving' => $validated['sla_resolving'],
    //         ]
    //     );

    //     $companies = $ticketType->companies()->get();

    //     return response([
    //         'companies' => $companies,
    //     ], 200);
    // }

    public function updateGroups(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'groups' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->groups()->sync($validated['groups']);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    public function deleteGroups(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'group_id' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->groups()->detach($validated['group_id']);

        $groups = $ticketType->groups()->get();

        return response([
            'groups' => $groups,
        ], 200);
    }

    public function createFormField(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'field_name' => 'required',
            'field_type' => 'required',
            'field_label' => 'required',
            'required' => 'required',
            'placeholder' => 'required',
            'hardware_limit' => 'required_if:field_type,hardware|integer',
            'include_no_type_hardware' => 'required_if:field_type,hardware|boolean',
            'hardware_types' => 'array|exists:hardware_types,id|nullable',
            'property_limit' => 'required_if:field_type,property|integer',
            'include_no_type_property' => 'required_if:field_type,property|boolean',
            'property_types' => 'array|nullable',
        ]);

        $fillableFields = array_merge(
            $request->only((new TypeFormFields)->getFillable())
        );

        $formField = TypeFormFields::create($fillableFields);

        if ($request['field_type'] == 'hardware') {
            $formField->hardwareTypes()->sync($validated['hardware_types']);
            // $formField->load('hardwareTypes');
        }

        if ($request['field_type'] == 'property') {
            // Property types are stored as comma-separated values since they are fixed (1-5)
            if (isset($validated['property_types']) && is_array($validated['property_types'])) {
                $formField->property_types = $validated['property_types'];
                $formField->save();
            }
        }

        return response([
            'formField' => $formField,
        ], 200);
    }

    public function deleteFormField($formFieldId, Request $request) {
        $user = $request->user();

        if (!$user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $formField = TypeFormFields::find($formFieldId);

        if (!$formField) {
            return response(['message' => 'Form field not found'], 404);
        }

        $formField->delete();

        return response(['message' => 'Form field deleted successfully'], 200);
    }


    public function countTicketsInCompany($ticketTypeId) {
        $ticketType = TicketType::where('id', $ticketTypeId)->first();
        $count = 0;
        // Non essendo più obbligatoria la compagnia, si deve controllare prima se è stata assegnata.
        if ($ticketType->company) {
            $count = $ticketType->countRelatedTickets();
        }
        return response([
            'count' => $count,
        ], 200);
    }

    public function countTicketsInType(TicketType $ticketType) {
        $tickets = $ticketType->tickets()->get();
        return response([
            'tickets' => $tickets,
        ], 200);
    }

    public function duplicateTicketType(Request $request) {

        $fields = $request->validate([
            'new_company_id' => 'required|numeric',
            'ticket_type_id' => 'required|numeric',
        ]);

        $user = $request->user();
        if (!$user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $ticketType = TicketType::where('id', $fields['ticket_type_id'])->first();
        $newTicketType = $ticketType->replicate();
        $newTicketType->company_id = $fields['new_company_id'];
        $success = $newTicketType->save();

        if (!$success) {
            return response([
                'message' => 'Error while duplicating ticket type',
            ], 500);
        }

        $newTicketType = TicketType::where('id', $newTicketType["id"])->with("category")->first();

        // Deve duplicare anche il webform e i gruppi
        TypeFormFields::where('ticket_type_id', $ticketType->id)->get()->each(function ($formField) use ($newTicketType) {
            $newFormField = $formField->replicate();
            $newFormField->ticket_type_id = $newTicketType->id;
            $newFormField->save();
            // Deve duplicare anche le associazioni coi tipi di hardware degli eventuali campi di tipo hardware
            if ($formField->field_type == 'hardware') {
                $newFormField->hardwareTypes()->sync($formField->hardwareTypes()->get());
            }
            // Deve duplicare anche i tipi di immobile degli eventuali campi di tipo property
            if ($formField->field_type == 'property') {
                $newFormField->property_types = $formField->property_types;
                $newFormField->save();
            }
        });

        $ticketType->groups()->get()->each(function ($group) use ($newTicketType) {
            $newTicketType->groups()->attach($group->id);
        });

        return response([
            'ticketType' => $newTicketType
        ], 200);
    }

    function getCustomGroups(TicketType $ticketType) {
        $customGroups = $ticketType->customGroups()->get();
        return response([
            'customGroups' => $customGroups,
        ], 200);
    }

    function getAvailableCustomGroups(TicketType $ticketType) {
        $customGroups = $ticketType->customGroups()->get();

        $company = Company::where('id', $ticketType->company_id)->first();

        $allCustomGroups = $company ? $company->customUserGroups()->get() : collect();

        $availableCustomGroups = $allCustomGroups->diff($customGroups);

        return response([
            'customGroups' => $availableCustomGroups,
        ], 200);
    }

    function addCustomGroup(Request $request) {
        $fields = $request->validate([
            'ticket_type_id' => 'required|numeric',
            'custom_user_group_ids' => 'required|json',
        ]);

        $group_ids = json_decode($fields['custom_user_group_ids']);

        $ticketType = TicketType::where('id', $fields['ticket_type_id'])->first();

        $ticketType->customGroups()->syncWithoutDetaching($group_ids);

        $customGroups = $ticketType->customGroups()->get();

        return response([
            'customGroups' => $customGroups,
        ], 200);
    }

    function removeCustomGroup(Request $request) {
        $fields = $request->validate([
            'ticket_type_id' => 'required|numeric',
            'custom_user_group_id' => 'required|numeric',
        ]);

        $ticketType = TicketType::where('id', $fields['ticket_type_id'])->first();
        $ticketType->customGroups()->detach($fields['custom_user_group_id']);

        $customGroups = $ticketType->customGroups()->get();

        return response([
            'customGroups' => $customGroups,
        ], 200);
    }

    function setCustomGroupExclusive(TicketType $ticketType, Request $request) {
        $fields = $request->validate([
            'is_custom_group_exclusive' => 'required|boolean',
        ]);

        $ticketType->update($fields);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    function context(TicketType $ticketType) {
       
        $ticketType->load('category', 'company');

        $context = [
            'company' => $ticketType->company,
            'category' => $ticketType->category,
            'type_id' => $ticketType->id,
            'isRequest' => $ticketType->category?->is_request,
            'isProblem' => $ticketType->category?->is_problem,
        ];

        return response([
            'context' => $context,
        ], 200);
    }

    function getSlaveTypes(TicketType $ticketType) {
        $slaveTypes = $ticketType->slaveTypes()->with('category')->get();

        return response([
            'slaveTypes' => $slaveTypes,
        ], 200);
    }

    function editSlaveTypes(TicketType $ticketType, Request $request) {
        /**
         * Expected structure:
         * slave_types = '[{"id": 1, "is_required": true}, {"id": 2, "is_required": false}]'
         */
        $fields = $request->validate([
            'slave_types' => 'required|json',
        ]);

        $slave_types = json_decode($fields['slave_types'], true);

        $pivotData = [];
        foreach ($slave_types as $slave) {
            $pivotData[$slave['id']] = ['is_required' => $slave['is_required']];
        }
        $ticketType->slaveTypes()->sync($pivotData);

        // Se uno degli slave associati ha is_required=1 nella pivot e it_referer_limited=1, aggiorna il master
        $updatedSlaves = $ticketType->slaveTypes()->get();
        $refererLimited = false;
        $warning = null;
        foreach ($updatedSlaves as $slave) {
            $pivot = $slave->pivot;
            if ($pivot && $pivot->is_required == 1 && $slave->it_referer_limited == 1) {
                $refererLimited = true;
                break;
            }
        }
        if ($refererLimited && ($ticketType->it_referer_limited != 1)) {
            $ticketType->it_referer_limited = 1;
            $ticketType->save();
            $warning = 'Attenzione: Il tipo principale è stato impostato come "Limitato ai referenti IT", perchè uno dei tipi associati è sia obbligatorio che limitato ai referenti IT.';
        }

        $slaveTypes = $ticketType->slaveTypes()->get();

        return response([
            'slaveTypes' => $slaveTypes,
            'warning' => $warning,
        ], 200);
    }

    // Prende tutti i possibili tipi di ticket slave 
    // che si possono associare a questo master type (compresi quelli già associati)
    function getAvailableSlaveTypes(TicketType $ticketType) {
        if (!$ticketType->is_master || !$ticketType->company()) {
            return response([
                'availableTypes' => [],
            ], 200);
        }
        $allTypes = TicketType::where([
            ['is_deleted', false],
            ['company_id', $ticketType->company_id],
            ['id', '!=', $ticketType->id],
            ['is_master', false],
            ['is_scheduling', false],
            ['is_grouping', false],
        ])
        ->with('category')
        ->get();

        // $availableSlaveTypes = $ticketType->slaveTypes()->get();
        // $availableTypes = $allTypes->diff($availableSlaveTypes);


        return response([
            'availableSlaveTypes' => $allTypes,
        ], 200);
    }
}

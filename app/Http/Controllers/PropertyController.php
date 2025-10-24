<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\TypeFormFields;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();

        if ($authUser->is_admin) {
            $properties = Property::with(['users', 'company'])->get();
        } elseif ($authUser->is_company_admin) {
            $selectedCompany = $authUser->selectedCompany();
            $properties = Property::with(['users', 'company'])
                ->where('company_id', $selectedCompany)
                ->get();
        } else {
            $selectedCompany = $authUser->selectedCompany();
            $properties = $authUser->properties()->with(['users', 'company'])->where('company_id', $selectedCompany)->whereHas('users', function ($query) use ($authUser) {
                $query->where('user_id', $authUser->id);
            })->get();
        }

        return response([
            'properties' => $properties,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response([
            'message' => 'Please use /api/store to create a new property.',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to create a property.',
            ], 403);
        }

        $data = $request->validate([
            'section' => 'required|string|max:255',
            'sheet' => 'required|string|max:255',
            'parcel' => 'required|string|max:255',
            'users_number' => 'nullable|integer',
            'energy_class' => 'required|string|max:10',
            'square_meters' => 'required|numeric|min:0',
            'thousandths' => 'required|numeric|min:0',
            'activity_type' => 'required|integer', // Assuming activity_type is an integer
            'in_use_by' => 'required|integer', // Assuming in_use_by is an integer
            'company_id' => 'nullable|exists:companies,id', // Assuming company_id is optional and must exist in companies table
        ]);

        $property = Property::create($data);

        return response([
            'message' => 'Property created successfully.',
            'property' => $property,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Property $property)
    {
        //

        return response([
            'property' => $property->load(['users', 'company']),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Property $property)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Property $property)
    {
        //

        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update this property.',
            ], 403);
        }

        $data = $request->validate([
            'section' => 'required|string|max:255',
            'sheet' => 'required|string|max:255',
            'parcel' => 'required|string|max:255',
            'users_number' => 'nullable|integer',
            'energy_class' => 'required|string|max:10',
            'square_meters' => 'required|numeric|min:0',
            'thousandths' => 'required|numeric|min:0',
            'activity_type' => 'required|integer', // Assuming activity_type is an integer
            'in_use_by' => 'required|integer', // Assuming in_use_by is an integer
            'company_id' => 'nullable|exists:companies,id', // Assuming company_id is optional and must exist in companies table
        ]);

        $property->update($data);

        return response([
            'message' => 'Property updated successfully.',
            'property' => $property->load(['users', 'company']),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Property $property)
    {
        $authUser = request()->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to delete this property.',
            ], 403);
        }

        $property->delete();

        return response([
            'message' => 'Property deleted successfully.',
        ], 200);
    }

    /**
     * Restore the specified resource from soft delete.
     */
    public function restore($id)
    {
        $authUser = request()->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to restore this property.',
            ], 403);
        }

        $property = Property::withTrashed()->findOrFail($id);
        $property->restore();

        return response([
            'message' => 'Property restored successfully.',
            'property' => $property->load(['users', 'company']),
        ], 200);
    }

    public function addUser(Request $request, Property $property)
    {
        $authUser = $request->user();

        if (! $authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to associate a user with this property.',
            ], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $property->users()->attach($data['user_id']);

        return response([
            'message' => 'User associated with property successfully.',
            'property' => $property->load(['users', 'company']),
        ], 200);
    }

    public function formFieldPropertyList(Request $request, TypeFormFields $typeFormField)
    {
        $authUser = $request->user();

        if (! $typeFormField) {
            return response([
                'message' => 'Type form field not found',
            ], 404);
        }

        $company = $typeFormField->ticketType->company;
        if (! $authUser->is_admin && ! ((bool) $company && $authUser->companies()->where('companies.id', $company->id)->exists())) {
            return response([
                'message' => 'You are not allowed to view these properties',
            ], 403);
        }

        // Costruisci la query di base
        if ($authUser->is_admin || $authUser->is_company_admin) {
            $query = Property::where('company_id', $company->id);
        } else {
            $query = $authUser->properties()->where('company_id', $company->id);
        }

        // Aggiungi le relazioni
        $query->with(['users', 'company']);

        // Se necessario rimuove gli immobili che non hanno il tipo associato
        if (! $typeFormField->include_no_type_property) {
            $query->whereNotNull('activity_type');
        }

        // Se necessario limitare a determinati tipi di immobile
        if (! empty($typeFormField->property_types)) {
            $propertyTypeIds = $typeFormField->property_types;
            $query->where(function ($query) use ($propertyTypeIds, $typeFormField) {
                $query->whereIn('activity_type', $propertyTypeIds);
                if ($typeFormField->include_no_type_property) {
                    $query->orWhereNull('activity_type');
                }
            });
        }

        // Esegui la query
        $propertyList = $query->get();

        return response([
            'propertyList' => $propertyList,
        ], 200);
    }
}

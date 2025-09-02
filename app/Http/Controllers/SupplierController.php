<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //

        return response([
            'suppliers' => Supplier::all()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //

        $supplier = Supplier::create([
            'name' => $request->name
        ]);

        return response([
            'supplier' => $supplier
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier) {
        //

        $supplier->logo_url = $supplier->logo_url != null ? Storage::disk('gcs')->temporaryUrl($supplier->logo_url, now()->addMinutes(70)) : '';


        return response([
            'supplier' => $supplier,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier) {
        //

        $supplier->update([
            'name' => $request->name
        ]);

        return response([
            'supplier' => $supplier
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier) {
        //
    }


    public function uploadLogo($id, Request $request) {

        if ($request->file('file') != null) {

            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();

            $path = "supplier/" . $id . "/logo/" . $file_name;

            $file->storeAs($path);

            $supplier = Supplier::find($id);
            $supplier->update([
                'logo_url' => $path
            ]);

            return response()->json([
                'supplier' => $supplier
            ]);
        }
    }

    public function generatedSignedUrlForFile($id) {

        $supplier = Supplier::find($id);

        $url = Storage::disk('gcs')->temporaryUrl(
            $supplier->logo_url,
            now()->addMinutes(65)
        );

        return response([
            'url' => $url,
        ], 200);
    }

    public function brands(Supplier $supplier) {

        return response([
            'brands' => $supplier->brands
        ], 200);
    }
}

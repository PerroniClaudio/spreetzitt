<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response([
            'suppliers' => Supplier::all(),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $supplier = Supplier::create([
            'name' => $request->name,
        ]);

        return response([
            'supplier' => $supplier,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        //

        $supplier->logo_url = $supplier->logo_url != null ? FileUploadController::generateSignedUrlForFile($supplier->logo_url, 70) : '';

        return response([
            'supplier' => $supplier,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        //

        $supplier->update([
            'name' => $request->name,
        ]);

        return response([
            'supplier' => $supplier,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        //
    }

    public function uploadLogo($id, Request $request)
    {
        if ($request->file('file') != null) {
            $file = $request->file('file');
            $file_name = time().'_'.$file->getClientOriginalName();
            $db_path = 'supplier/'.$id.'/logo';
            $bucket_path = FileUploadController::storagePathPrefix().'supplier/'.$id.'/logo';
            FileUploadController::storeFile($file, $bucket_path, $file_name);
            $supplier = Supplier::find($id);
            $supplier->update([
                'logo_url' => $db_path.'/'.$file_name,
            ]);
            return response()->json([
                'supplier' => $supplier,
            ]);
        }
    }

    public function generatedSignedUrlForFile($id)
    {

        $supplier = Supplier::find($id);

        $url = FileUploadController::generateSignedUrlForFile($supplier->logo_url);

        return response([
            'url' => $url,
        ], 200);
    }

    public function brands(Supplier $supplier)
    {

        return response([
            'brands' => $supplier->brands,
        ], 200);
    }

    public function getLogo(Supplier $supplier)
    {
        $disk = FileUploadController::getStorageDisk();
        $imagePath = $supplier->logo_url;

        if ($disk === 'public' || $disk === 'local') {
            // Se il disco è locale, costruiamo il path assoluto
            $imageContent = file_get_contents(Storage::path($imagePath));
        } else {
            // Per i dischi cloud, generiamo un URL firmato e scarichiamo il contenuto
            $imageUrl = FileUploadController::generateSignedUrlForFile($imagePath);
            $imageContent = file_get_contents($imageUrl);
        }

        // Restituisci l'immagine come risposta HTTP con il tipo di contenuto image/jpeg
        return response($imageContent, 200, [
            'Content-Type' => 'image/jpeg',
        ]);
    }
}

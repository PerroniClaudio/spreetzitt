<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response([
            'brands' => Brand::all(),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $brand = Brand::create([
            'name' => $request->name,
        ]);

        return response([
            'brand' => $brand,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Brand $brand)
    {
        //

        $brand->logo_url = $brand->logo_url != null ? FileUploadController::generateSignedUrlForFile($brand->logo_url, 70) : '';

        return response([
            'brand' => $brand,

        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Brand $brand)
    {
        //

        $brand->update([
            'name' => $request->name,
            'supplier_id' => $request->supplier_id,
        ]);

        return response([
            'brand' => $brand,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Brand $brand)
    {
        //
    }

    public function uploadLogo($id, Request $request)
    {

        if ($request->file('file') != null) {

            $file = $request->file('file');
            $file_name = time().'_'.$file->getClientOriginalName();

            $db_path = 'brands/'.$id.'/logo';
            $bucket_path = 'tickets/brands/'.$id.'/logo';
            FileUploadController::storeFile($file, $bucket_path, $file_name);
            $brand = Brand::find($id);
            $brand->update([
                'logo_url' => $db_path.'/'.$file_name,
            ]);
            return response()->json([
                'brand' => $brand,
            ]);
        }
    }

    public function generatedSignedUrlForFile($id)
    {

        $brand = Brand::find($id);

        $url = FileUploadController::generateSignedUrlForFile($brand->logo_url);

        return response([
            'url' => $url,
        ], 200);
    }

    public function getLogo(Brand $brand)
    {
        $disk = FileUploadController::getStorageDisk();
        $imagePath = $brand->logo_url;

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

    public function getLogos()
    {
        $brands = Brand::whereNotNull('logo_url')->get();
        $disk = FileUploadController::getStorageDisk();

        foreach ($brands as $brand) {
            if ($disk === 'public' || $disk === 'local') {
                $brand->logo_url = config('app.url').'/api/brand/'.$brand->id.'/logo';
            } else {
                $brand->logo_url = FileUploadController::generateSignedUrlForFile($brand->logo_url, 70);
            }
        }

        return response([
            'brands' => $brands,
        ], 200);
    }
}

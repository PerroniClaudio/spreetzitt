<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //

        return response([
            'brands' => Brand::all()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //

        $brand = Brand::create([
            'name' => $request->name
        ]);

        return response([
            'brand' => $brand
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Brand $brand) {
        //

        $brand->logo_url = $brand->logo_url != null ? Storage::disk('gcs')->temporaryUrl($brand->logo_url, now()->addMinutes(70)) : '';

        return response([
            'brand' => $brand,

        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Brand $brand) {
        //

        $brand->update([
            'name' => $request->name,
            'supplier_id' => $request->supplier_id
        ]);

        return response([
            'brand' => $brand
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Brand $brand) {
        //
    }

    public function uploadLogo($id, Request $request) {

        if ($request->file('file') != null) {

            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();

            $path = "brands/" . $id . "/logo/" . $file_name;

            $file->storeAs($path);

            $brand = Brand::find($id);
            $brand->update([
                'logo_url' => $path
            ]);

            return response()->json([
                'brand' => $brand
            ]);
        }
    }

    public function generatedSignedUrlForFile($id) {

        $brand = Brand::find($id);

        $url = Storage::disk('gcs')->temporaryUrl(
            $brand->logo_url,
            now()->addMinutes(65)
        );

        return response([
            'url' => $url,
        ], 200);
    }

    public function getLogo(Brand $brand) {
        // header("Content-type: image/jpeg"); //(così che viene settato l'header della risposta)
        // $url = Storage::disk('gcs')->temporaryUrl(
        //     $brand->logo_url,
        //     now()->addMinutes(65)
        // );
        // imagejpeg($url);

        //Query per l'immagine che ti serve
        $imagePath = $brand->logo_url; 
        // Genera l'URL temporaneo per l'immagine nel bucket
        $imageUrl = Storage::disk('gcs')->temporaryUrl($imagePath, now()->addMinutes(65));
        // Scarica l'immagine dal bucket
        $imageContent = file_get_contents($imageUrl);
        // Restituisci l'immagine come risposta HTTP con il tipo di contenuto image/jpeg
        return response($imageContent, 200, [
            'Content-Type' => 'image/jpeg',
        ]);
    }
}

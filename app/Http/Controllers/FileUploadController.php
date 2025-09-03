<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    /**
     * Determina quale disco utilizzare per l'upload dei file
     * basandosi sulla variabile d'ambiente OBJECT_STORAGE_METHOD
     */
    public static function getStorageDisk(): string
    {
        $method = config('app.object_storage_method', 'default');

        return $method === 'gcs' ? 'gcs' : config('filesystems.default');
    }

    /**
     * Carica un file utilizzando il disco appropriato
     */
    public static function storeFile(UploadedFile $file, string $path, ?string $fileName = null): string
    {
        $disk = self::getStorageDisk();
        $fileName = $fileName ?: time().'_'.$file->getClientOriginalName();

        return $file->storeAs($path, $fileName, $disk);
    }

    public function uploadFileToCloud(Request $request)
    {
        try {
            $file = $request->file('file');
            $file_name = time().'_'.$file->getClientOriginalName();
            $storeFile = self::storeFile($file, 'test', $file_name);

            // Usa il disco appropriato per ottenere l'URL
            $disk = self::getStorageDisk();
            if ($disk === 'gcs') {
                $diskInstance = Storage::disk('gcs');
                $fetchFile = $diskInstance->url($storeFile);
            } else {
                $fetchFile = Storage::url($storeFile);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante l\'upload del file',
            ], 500);
        }

        return response()->json([
            'data' => $fetchFile,
        ], 201);
    }
}

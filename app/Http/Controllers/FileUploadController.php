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

        if ($disk === 'local') {
            // Se il disco è 'local', salviamo in 'storage/app/public'
            return $file->storeAs('public/'.$path, $fileName, $disk);
        }

        return $file->storeAs($path, $fileName, $disk);
    }

    /**
     * Genera un URL temporaneo per scaricare un file
     * basandosi sul disco configurato
     */
    public static function generateSignedUrlForFile(string $filePath, int $minutesValid = 65, array $options = []): string
    {
        $disk = self::getStorageDisk();

        if ($disk === 'gcs') {
            // Per Google Cloud Storage, usa temporaryUrl
            /**
             * @disregard Intelephense non rileva il metodo temporaryUrl
             */
            return Storage::disk('gcs')->temporaryUrl($filePath, now()->addMinutes($minutesValid), $options);
        } elseif ($disk === 'private' || $disk === 's3') {
            // Per Cloudflare R2 o AWS S3, usa temporaryUrl con options
            /**
             * @disregard Intelephense non rileva il metodo temporaryUrl
             */
            return Storage::disk($disk)->temporaryUrl('tickets/' . $filePath, now()->addMinutes($minutesValid), $options);
        } else {
            // Per il disco locale, ritorna il path diretto
            // In produzione potresti voler implementare una logica diversa
            
            return Storage::url($filePath);
        }
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
                /**
                 * @disregard Intelephense non rileva il metodo url
                 */
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

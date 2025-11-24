<?php

namespace App\Models;

use App\Http\Controllers\FileUploadController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class HardwareAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hardware_id',
        'file_path',
        'original_filename',
        'display_name',
        'file_extension',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    /**
     * Relazione con Hardware
     */
    public function hardware()
    {
        return $this->belongsTo(Hardware::class);
    }

    /**
     * Relazione con User (uploader)
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Nome del file per il download
     * Usa display_name se presente, altrimenti original_filename
     */
    public function downloadFilename(): string
    {
        $baseName = $this->display_name ?? pathinfo($this->original_filename, PATHINFO_FILENAME);
        
        // Prepara l'estensione (aggiungi punto se manca)
        $extension = $this->file_extension;
        if (!str_starts_with($extension, '.')) {
            $extension = '.' . $extension;
        }
        
        // Assicura che abbia l'estensione corretta
        if (!str_ends_with(strtolower($baseName), strtolower($extension))) {
            return $baseName . $extension;
        }
        
        return $baseName;
    }

    /**
     * Genera URL temporaneo per download con nome corretto
     */
    public function getDownloadUrl(int $minutesValid = 5): string
    {
        // Header per forzare il nome file nel download
        $options = [
            'ResponseContentDisposition' => 'attachment; filename="' . $this->downloadFilename() . '"'
        ];
        
        return FileUploadController::generateSignedUrlForFile($this->file_path, $minutesValid, $options);
    }

    /**
     * Genera URL temporaneo per preview (inline)
     */
    public function getPreviewUrl(int $minutesValid = 5): string
    {
        // Header per visualizzare inline nel browser
        $options = [
            'ResponseContentDisposition' => 'inline; filename="' . $this->downloadFilename() . '"'
        ];
        
        return FileUploadController::generateSignedUrlForFile($this->file_path, $minutesValid, $options);
    }

    /**
     * Verifica se il file è un'immagine
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Verifica se il file è un PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Dimensione file formattata (es: "2.5 MB")
     */
    public function getFormattedSize(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Elimina il file da storage solo quando si fa force delete
     */
    protected static function boot()
    {
        parent::boot();

        // Solo con force delete elimina fisicamente il file da storage
        static::forceDeleting(function ($attachment) {
            $disk = FileUploadController::getStorageDisk();
            if (Storage::disk($disk)->exists($attachment->file_path)) {
                Storage::disk($disk)->delete($attachment->file_path);
            }
        });
    }
}

<?php

namespace App\Models;

use App\Http\Controllers\FileUploadController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SoftwareAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'software_id',
        'file_path',
        'original_filename',
        'display_name',
        'file_extension',
        'mime_type',
        'file_size',
        'uploaded_by',
        'access_level',
        'uploaded_by_level',
    ];

    /**
     * Boot method per impostare uploaded_by_level automaticamente
     */
    protected static function boot()
    {
        parent::boot();

        // Imposta uploaded_by_level al momento della creazione
        static::creating(function ($attachment) {
            if (!$attachment->uploaded_by_level && $attachment->uploader) {
                $attachment->uploaded_by_level = $attachment->uploader->getUserLevel();
            }
        });

        // Solo con force delete elimina fisicamente il file da storage
        static::forceDeleting(function ($attachment) {
            $disk = FileUploadController::getStorageDisk();
            if (Storage::disk($disk)->exists($attachment->file_path)) {
                Storage::disk($disk)->delete($attachment->file_path);
            }
        });
    }

    /**
     * Scope per filtrare allegati accessibili da un utente.
     * Restituisce solo gli allegati che l'utente può visualizzare in base al suo livello.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $userLevelValue = $user->getUserLevelValue();
        
        // Ottieni tutti i livelli accessibili (con valore >= utente)
        // Es: admin (2) può accedere a: admin (2), company_admin (3), user (4)
        $accessibleLevels = collect(config('permissions.access_levels'))
            ->filter(fn($value) => $value >= $userLevelValue)
            ->keys()
            ->toArray();
        
        return $query->whereIn('access_level', $accessibleLevels);
    }

    /**
     * Verifica se un utente può visualizzare questo allegato
     */
    public function canView(User $user): bool
    {
        $userLevelValue = $user->getUserLevelValue();
        return $userLevelValue <= User::getLevelValue($this->access_level);
    }

    /**
     * Verifica se un utente può modificare l'access_level di questo allegato
     * Deve soddisfare ENTRAMBE le condizioni:
     * 1. Avere livello >= uploaded_by_level (es: admin può modificare file caricati da company_admin)
     * 2. Poter visualizzare il file (se access_level è ristretto, non puoi modificarlo)
     */
    public function canModifyAccessLevel(User $user): bool
    {
        $userLevelValue = $user->getUserLevelValue();
        
        // Deve avere privilegi >= a chi ha caricato il file
        $canModifyByUploader = $userLevelValue <= User::getLevelValue($this->uploaded_by_level);
        
        // Deve poter vedere il file (access_level)
        $canSeeFile = $this->canView($user);
        
        // Entrambe le condizioni devono essere vere
        return $canModifyByUploader && $canSeeFile;
    }

    /**
     * Verifica se un utente può eliminare questo allegato
     */
    public function canDelete(User $user): bool
    {
        // Stesse regole della modifica access_level
        return $this->canModifyAccessLevel($user);
    }

    /**
     * Relazione con Software
     */
    public function software()
    {
        return $this->belongsTo(Software::class);
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
}

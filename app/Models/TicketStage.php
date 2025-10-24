<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'admin_color',
        'user_color',
        'order',
        'is_sla_pause',
        'is_system',
    ];

    protected $casts = [
        'is_sla_pause' => 'boolean',
        'is_system' => 'boolean',
        'order' => 'integer',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'stage_id');
    }

    /**
     * Scope per ottenere solo gli stage attivi (non soft deleted)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope per ordinare per campo order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    /**
     * Scope per ottenere solo gli stage di sistema
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope per ottenere solo gli stage modificabili dall'utente
     */
    public function scopeUserManaged($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Trova uno stage di sistema tramite la sua chiave
     */
    public static function getSystemStage(string $systemKey): ?self
    {
        return self::where('system_key', $systemKey)->first();
    }

    /**
     * Override del delete per impedire l'eliminazione degli stage di sistema
     */
    public function delete()
    {
        if ($this->is_system) {
            throw new \Exception('Cannot delete system ticket stage: '.$this->name);
        }

        return parent::delete();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsSource extends Model
{
    use HasFactory;

    /**
     * Tipi possibili per una NewsSource.
     *
     * - internal_blog: Blog interno aziendale
     * - vendor_blog: Blog di un vendor esterno
     * - vendor_social: Social network di un vendor (es. Twitter, LinkedIn)
     * - rss: Feed RSS/Atom generico
     * - manual: Inserimento manuale
     * - other: Altro (specificare nella descrizione)
     */
    public const TYPES = [
        'internal_blog',
        'vendor_blog',
        'vendor_social',
        'rss',
        'manual',
        'other',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'display_name',
        'slug',
        'type',
        'url',
        'description',
    ];

    /**
     * Get the news for the source.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function news(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(News::class);
    }

    /**
     * Get the tokens for the source.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NewsSourceToken::class);
    }
}

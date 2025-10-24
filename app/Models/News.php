<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'news_source_id',
        'title',
        'url',
        'description',
        'published_at',
    ];

    /**
     * Get the source that owns the news.
     */
    public function source(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }
}

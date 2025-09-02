<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class WikiObject extends Model {
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'uploaded_name',
        'type',
        'mime_type',
        'path',
        'is_public',
        'company_id',
        'uploaded_by',
        'file_size',
    ];

    public function toSearchableArray() {
        return [
            'name' => $this->name,
            'uploaded_name' => $this->uploaded_name,
        ];
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function user() {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

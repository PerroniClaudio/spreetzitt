<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Brand extends Model {
    use HasFactory;

    protected $fillable = ['name', 'description', 'logo_url', 'supplier_id'];

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function withGUrl() {
        $this->logo_url = $this->logo_url != null ? Storage::disk('gcs')->temporaryUrl($this->logo_url, now()->addMinutes(70)) : '';
        // $url = $this->logo_url != null ? Storage::disk('gcs')->temporaryUrl($this->logo_url, now()->addMinutes(70)) : '';
        // $this->logo_url = str_replace('%5C', '/', $url);
        return $this;
    }
}

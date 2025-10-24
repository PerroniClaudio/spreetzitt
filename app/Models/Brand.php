<?php

namespace App\Models;

use App\Http\Controllers\FileUploadController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'logo_url', 'supplier_id'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function withGUrl()
    {
        $this->logo_url = $this->logo_url != null ? FileUploadController::generateSignedUrlForFile($this->logo_url, 70) : '';

        return $this;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Software extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'software';

    protected $fillable = [
        'vendor',
        'product_name',
        'version',
        'activation_key',
        'company_asset_number',
        'is_exclusive_use',
        'license_type',
        'max_installations',
        'purchase_date',
        'expiration_date',
        'support_expiration_date',
        'status',
        'company_id',
        'software_type_id',
    ];

    protected static function boot()
    {
        parent::boot();

        // Aggiunge un log quando viene creato un nuovo software
        static::created(function ($model) {
            SoftwareAuditLog::create([
                'log_subject' => 'software',
                'log_type' => 'create',
                'modified_by' => auth()->id(),
                'software_id' => $model->id,
                'old_data' => null,
                'new_data' => json_encode($model->toArray()),
            ]);
        });

        // Aggiunge un log quando viene modificato un software
        static::updating(function ($model) {
            $model->updated_at = now();

            $originalData = $model->getOriginal();
            $updatedData = $model->toArray();

            // Trasforma l'eventuale array di oggetti "users" in array di numeri (id)
            foreach ($updatedData as $key => $value) {
                if (is_array($value) && isset($value[0]) && is_object($value[0])) {
                    if ($key == 'users') {
                        $updatedData[$key] = array_map(function ($item) {
                            return $item->id;
                        }, $value);
                    } else {
                        unset($updatedData[$key]);
                    }
                }
            }

            SoftwareAuditLog::create([
                'log_subject' => 'software',
                'log_type' => 'update',
                'modified_by' => auth()->id(),
                'software_id' => $model->id,
                'old_data' => json_encode($originalData),
                'new_data' => json_encode($updatedData),
            ]);
        });

        // Aggiunge un log quando viene eliminato un software (soft delete)
        static::deleting(function ($model) {
            $deleteType = $model->isForceDeleting() ? 'force_delete' : 'soft_delete';

            $model->users = $model->users()->pluck('user_id')->toArray();
            SoftwareAuditLog::create([
                'log_subject' => 'software',
                'log_type' => $deleteType,
                'modified_by' => auth()->id(),
                'software_id' => $model->id,
                'old_data' => json_encode($model->toArray()),
                'new_data' => null,
            ]);
        });

        // Aggiunge un log quando viene ripristinato un software
        static::restored(function ($model) {
            $model->users = $model->users()->pluck('user_id')->toArray();
            SoftwareAuditLog::create([
                'log_subject' => 'software',
                'log_type' => 'restore',
                'modified_by' => auth()->id(),
                'software_id' => $model->id,
                'old_data' => null,
                'new_data' => json_encode($model->toArray()),
            ]);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function softwareType()
    {
        return $this->belongsTo(SoftwareType::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'software_user')
            ->withTimestamps();
    }

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class, 'software_ticket')
            ->withTimestamps();
    }

    public function attachments()
    {
        return $this->hasMany(SoftwareAttachment::class);
    }
}

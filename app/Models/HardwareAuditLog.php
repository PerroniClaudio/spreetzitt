<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareAuditLog extends Model
{
    // Specifica il nome della tabella
    protected $table = 'hardware_audit_log';

    // è stato deciso che i dati dell'hardware dismesso non gli servono,
    // quindi, dal momento che l'hardware resta nel gestionale finchè non viene dismesso, si può tenere hardware_id come foreign key.
    // Gli utenti non vengono eliminati, quindi anche user_id si può tenere come foreign key.
    protected $fillable = [
        'modified_by', // Foreign key to User table (id)
        'hardware_id', // Foreign key to Hardware table (id).
        'old_data',
        'new_data',
        'log_subject', //hardware, hardware_user, hardware_company
        'log_type', //create, delete, update, permanent-delete
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    // public function user()
    // {
    //     if ($this->log_subject !== 'hardware_user') {
    //         return null;
    //     }
    //     if ($this->log_type === 'delete') {
    //         $oldData = $this->old_data();
    //         $userId = $oldData['user_id'];
    //         $user = User::find($userId);

    //         return $user;
    //     }
    //     if ($this->log_type === 'create') {
    //         $newData = $this->new_data();
    //         $userId = $newData['user_id'];
    //         $user = User::find($userId);

    //         return $user;
    //     }

    //     return null;
    // }

    public function hardware()
    {
        return $this->belongsTo(Hardware::class);
    }

    public function company()
    {
        if ($this->log_subject !== 'hardware_company') {
            return null;
        }
        if ($this->log_type === 'delete') {
            $oldData = $this->old_data();
            $companyId = $oldData['company_id'];
            $company = Company::find($companyId);

            return $company;
        }
        if ($this->log_type === 'create') {
            $newData = $this->new_data();
            $companyId = $newData['company_id'];
            $company = Company::find($companyId);

            return $company;
        }

        return null;
    }

    public function oldData()
    {
        return json_decode($this->old_data, true);
    }

    public function newData()
    {
        return json_decode($this->new_data, true);
    }
}

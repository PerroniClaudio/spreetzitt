<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoftwareAuditLog extends Model
{
    protected $table = 'software_audit_log';

    protected $fillable = [
        'modified_by',
        'software_id',
        'old_data',
        'new_data',
        'log_subject', // software, software_user, software_company
        'log_type', // create, delete, update, permanent-delete
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function old_data()
    {
        return json_decode($this->old_data, true);
    }

    public function new_data()
    {
        return json_decode($this->new_data, true);
    }

    // public function user()
    // {
    //     if ($this->log_subject !== 'software_user') {
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
}

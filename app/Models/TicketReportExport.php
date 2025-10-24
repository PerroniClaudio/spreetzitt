<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketReportExport extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'start_date',
        'end_date',
        'optional_parameters',
        'company_id',
        'is_generated',
        'is_user_generated',
        'is_failed',
        'error_message',
    ];

    use HasFactory;
}

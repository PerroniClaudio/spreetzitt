<?php

namespace App\Models;

use App\Http\Controllers\FileUploadController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'sla',
        'note',
        'sla_take_low',
        'sla_take_medium',
        'sla_take_high',
        'sla_take_critical',
        'sla_solve_low',
        'sla_solve_medium',
        'sla_solve_high',
        'sla_solve_critical',
        'sla_prob_take_low',
        'sla_prob_take_medium',
        'sla_prob_take_high',
        'sla_prob_take_critical',
        'sla_prob_solve_low',
        'sla_prob_solve_medium',
        'sla_prob_solve_high',
        'sla_prob_solve_critical',
        'data_owner_name',
        'data_owner_surname',
        'data_owner_email',
        'logo_url',
        'reading_delay_start',
        'reading_delay_notice',
        'privacy_policy_path',
        'cookie_policy_path',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user', 'company_id', 'user_id');
    }

    // public function ticketTypes() {
    //     return $this->belongsToMany(TicketType::class, 'company_ticket_types')->withPivot('sla_taking_charge', 'sla_resolving');;
    // }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    // Tutti i ticket dell'azienda
    public function tickets()
    {
        return $this->hasMany(Ticket::class)->with([
            'user' => function ($query) {
                $query->select(['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
            },
            'user.companies:id,name',
        ]);
    }

    // Tutti i progetti (ticket di tipo progetto) dell'azienda
    public function projects()
    {
        return $this->hasMany(Ticket::class)
            ->whereHas('ticketType', function ($query) {
                $query->where('is_project', true);
            })
            ->with([
                'user' => function ($query) {
                    $query->select(['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
                },
                'user.companies:id,name',
            ]);
    }

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function expenses()
    {
        return $this->hasMany(BusinessTripExpense::class);
    }

    public function transfers()
    {
        return $this->hasMany(BusinessTripTransfer::class);
    }

    public function brands()
    {
        return $this->ticketTypes()->get()->map(function ($ticketType) {
            return Brand::where('id', $ticketType->brand_id)->first();
        })->unique('id');
    }

    public function hardware()
    {
        return $this->hasMany(Hardware::class);
    }

    public function weeklyTimes()
    {
        return $this->hasMany(WeeklyTime::class);
    }

    public function temporaryLogoUrl()
    {
        if ($this->logo_url) {
            return FileUploadController::generateSignedUrlForFile($this->logo_url, 70);
        }

        return '';
    }

    public function temporaryPrivacyPolicyUrl()
    {
        if ($this->privacy_policy_path) {
            return FileUploadController::generateSignedUrlForFile($this->privacy_policy_path, 70);
        }

        return '';
    }

    public function temporaryCookiePolicyUrl()
    {
        if ($this->cookie_policy_path) {
            return FileUploadController::generateSignedUrlForFile($this->cookie_policy_path, 70);
        }

        return '';
    }

    public function customUserGroups()
    {
        return $this->hasMany(CustomUserGroup::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The news sources available to the company.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function newsSources()
    {
        return $this->belongsToMany(NewsSource::class, 'company_news_sources')
            ->withPivot('enabled')
            ->withTimestamps();
    }
}

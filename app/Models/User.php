<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Mail\OtpEmail;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'surname',
        'phone',
        'city',
        'zip_code',
        'address',
        'is_admin',
        'company_id',
        'is_company_admin',
        'microsoft_token',
        'is_deleted',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the company that owns the user.
     */

    public function companies() {
        return $this->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id');
    }

    public function hasCompany($companyId) {
        return $this->companies()->where('company_id', $companyId)->exists();
    }

    public function properties() {
        return $this->belongsToMany(Property::class, 'properties_users', 'user_id', 'property_id');
    }

    /**
     * get user's tickets
     */

    public function tickets() {
        return $this->hasMany(Ticket::class)->with([
            'user' => function ($query) {
                $query->select(['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
            },
            'user.companies:id,name'
        ]);
    }

    /**
     * get user's tickets as referer (seen in the webform message)
     */
    public function refererTickets() {
        $companiesIds = $this->companies()->pluck('companies.id')->all();
        if (empty($companiesIds)) {
            return collect(); // Return an empty collection if the user has no companies
        }
        $tickets = Ticket::whereIn('company_id', $companiesIds)
            ->where('referer_id', $this->id)
            ->with([
                'user' => function ($query) {
                    $query->select(['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
                },
                'company' => function ($query) {
                    $query->select(['id', 'name', 'logo_url']); // Specify the columns you want to include
                },
                'referer' => function ($query) {
                    $query->select(['id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
                }
            ])->get();
        return $tickets;
    }

    /**
     * get user's tickets merge as user and as referer (seen in the webform message)
     */
    public function ownTicketsMerged() {
        $ticketsTemp = $this->tickets()->with([
                'user' => function ($query) {
                    $query->select(['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
                },
                'company' => function ($query) {
                    $query->select(['id', 'name', 'logo_url']); // Specify the columns you want to include
                },
                'referer' => function ($query) {
                    $query->select(['id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
                }
            ])->get()
            ->merge($this->refererTickets());
        foreach ($ticketsTemp as $ticket) {
            // Nascondere i dati utente se è stato aperto dal supporto. Essendo lato admin al momento non serve
            if ($ticket->user->is_admin) {
                $ticket->user->id = 1;
                $ticket->user->name = "Supporto";
                $ticket->user->surname = "";
                $ticket->user->email = "Supporto";
            }
        }
        return $ticketsTemp;
    }


    /**
     * get user's groups
     */

    public function groups() {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id', 'group_id');
    }

    /**
     * get user's custom groups
     */

    public function customUserGroups() {
        return $this->belongsToMany(CustomUserGroup::class, 'user_custom_groups', 'user_id', 'custom_user_group_id');
    }

    /**
     * get user's attendances
     */

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }

    /**
     * get user's time off requests
     */

    public function timeOffRequests() {
        return $this->hasMany(TimeOffRequest::class);
    }

    public function businessTrips() {
        return $this->hasMany(BusinessTrip::class);
    }

    public function hardware() {
        return $this->belongsToMany(Hardware::class, 'hardware_user', 'user_id', 'hardware_id')
            ->using(HardwareUser::class)
            ->withPivot('created_by', 'responsible_user_id', 'created_at', 'updated_at');
    }

    public function createOtp() {
        // $otp = Otp::create([
        //     'email' => $this->email,
        //     'otp' => rand(1000, 9999),
        //     'expires_at' => now()->addMinutes(120),
        // ]);

        // Mail::to($this->email)->send(new OtpEmail($otp->otp));

        // return $otp;
        return true;
    }

    public function dashboard() {
        return $this->hasOne(Dashboard::class);
    }

    /**
     * Assignment history records associated to the admin user.
     */
    public function assignmentHistoryRecords()
    {
        return $this->hasMany(TicketAssignmentHistoryRecord::class, 'admin_user_id');
    }

    public function selectedCompany() {
        $selectedCompanyId = session('selected_company_id');
        $company = null;

        if ($selectedCompanyId) {
            // Cerca la company selezionata nella sessione
            $company = $this->companies()->find($selectedCompanyId);
        }

        if (!$company) {
            // Prendi la prima company associata
            $company = $this->companies()->first();
            if ($company) {
                // Aggiorna la sessione
                session(['selected_company_id' => $company->id]);
            }
        }

        return $company;
    }
}

<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Lab404\Impersonate\Services\ImpersonateManager;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use Notifiable, Impersonate, LogsActivity;

    protected static $logAttributes = ['id', 'name', 'is_admin', 'email', 'campaigns', 'companies'];

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'is_admin',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The roles that belong to the user.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class)->using(CompanyUser::class)->withPivot('role', 'config', 'completed_at');
    }

    /**
     * The roles that belong to the user.
     */
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function isAdmin(): bool
    {
        return (bool)$this->is_admin;
    }

    public function isCompanyAdmin(int $companyId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        $company = $this->companies()->find($companyId);
        if (empty($company) || $company->pivot->role != self::ROLE_ADMIN) {
            return false;
        }
        return true;
    }

    public function isCompanyUser(int $companyId): bool
    {
        $company = $this->companies()->find($companyId);
        if (empty($company) || $company->pivot->role != self::ROLE_USER) {
            return false;
        }
        return true;
    }

    public function getCampaigns(int $companyId)
    {
        return DB::table('campaigns')
                 ->select('campaigns.*')
                 ->join('campaign_user', 'campaign_user.campaign_id', '=', 'campaigns.id')
                 ->where('campaign_user.user_id', $this->id)
                 ->where(function($query) use ($companyId) {
                        $query->where('campaigns.agency_id', $companyId)
                        ->orWhere('campaigns.dealership_id', $companyId);
                 })
                 ->get();
    }

    public function hasAccessToCampaign(int $campaignId)
    {
        $company = $this->campaigns()->find($campaignId);
        if (empty($company)) {
            return false;
        }
        return true;
    }

    public function getPossibleTimezones()
    {
        return [
            'US/Alaska',
            'US/Aleutian',
            'US/Arizona',
            'US/Central',
            'US/East-Indiana',
            'US/Eastern',
            'US/Hawaii',
            'US/Indiana-Starke',
            'US/Michigan',
            'US/Mountain',
            'US/Pacific',
            'US/Pacific-New',
            'US/Samoa',
        ];
    }

    /**
     * Check if the current user is impersonated.
     *
     * @param   void
     * @return  bool
     */
    public function getImpersonarotId()
    {
        return app(ImpersonateManager::class)->getImpersonatorId();
    }
}

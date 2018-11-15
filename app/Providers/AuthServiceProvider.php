<?php

namespace App\Providers;

use App\Campaign;
use App\Company;
use App\ImpersonatedUser;
use App\Policies\CampaignPolicy;
use App\Policies\CompanyPolicy;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        Company::class => CompanyPolicy::class,
        Campaign::class => CampaignPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        $this->defineGates();

        $this->defineImpersonateAuthGate();
    }

    private function defineGates(): void
    {
        Gate::before(function (User $user, $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        Gate::define('company.viewforpreferences', 'App\Policies\CompanyPolicy@viewForPreferences');
        Gate::define('company.view', 'App\Policies\CompanyPolicy@view');
        Gate::define('company.create', 'App\Policies\CompanyPolicy@create');
        Gate::define('company.update', 'App\Policies\CompanyPolicy@update');
        Gate::define('company.delete', 'App\Policies\CompanyPolicy@delete');

        Gate::define('company.manage', 'App\Policies\CompanyPolicy@manage');


        Gate::define('campaign.create', 'App\Policies\CampaignPolicy@create');
        Gate::define('campaign.manage', 'App\Policies\CampaignPolicy@manage');
    }

    private function defineImpersonateAuthGate(): void
    {
        Auth::viaRequest('impersonuser', function () {
            /** @var User $user */
            $user = Auth::user();
            if ($user->isImpersonated()) {
                return ImpersonatedUser::findOrCreateImpersonatedUser($user->id, $user->getImpersonarotId());
            } else {
                return $user;
            }
        });
    }


}

<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            $roles = method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->map(fn ($role) => strtolower((string) $role))->all()
                : [];

            return in_array('super admin', $roles, true) || in_array('administrator', $roles, true)
                ? true
                : null;
        });

        Gate::define('view_all_branches', function ($user) {
            $roles = method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->map(fn ($role) => strtolower((string) $role))->all()
                : [];

            return in_array('owner', $roles, true)
                || in_array('super admin', $roles, true)
                || in_array('administrator', $roles, true);
        });
    }

}

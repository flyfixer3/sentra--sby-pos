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

        $guard = config('auth.defaults.guard', 'web');

        Gate::before(function ($user, $ability) use ($guard) {
            return $user->hasRole('Super Admin', $guard) ? true : null;
        });

        Gate::define('view_all_branches', function ($user) use ($guard) {
            return $user->hasAnyRole(['Owner', 'Super Admin'], $guard);
            // kalau ada role lain:
            // || $user->hasRole('Developer', $guard);
        });
    }
}

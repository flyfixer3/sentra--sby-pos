<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SetActiveBranch
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $availableBranches = $user->allAvailableBranches();

        // kalau user gak punya cabang sama sekali, biarkan request lanjut
        if ($availableBranches->count() === 0) {
            return $next($request);
        }

        $active = Session::get('active_branch');

        // 1) kalau belum ada active_branch, set ke cabang pertama
        if (!$active) {
            Session::put('active_branch', $availableBranches->first()->id);
            return $next($request);
        }

        // 2) kalau active_branch = all, cek permission
        if ($active === 'all') {
            if (!$user->can('view_all_branches')) {
                Session::put('active_branch', $availableBranches->first()->id);
            }
            return $next($request);
        }

        // 3) active_branch numeric, validasi harus ada dalam available branches
        $activeId = (int) $active;
        $exists = $availableBranches->contains(function ($branch) use ($activeId) {
            return (int) $branch->id === $activeId;
        });

        if (!$exists) {
            Session::put('active_branch', $availableBranches->first()->id);
        }

        return $next($request);
    }
}

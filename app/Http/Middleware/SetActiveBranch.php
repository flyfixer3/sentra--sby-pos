<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SetActiveBranch
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Jika belum ada cabang aktif di session, isi otomatis
            if (!Session::has('active_branch')) {
                $firstBranch = $user->allAvailableBranches()->first();
                if ($firstBranch) {
                    Session::put('active_branch', $firstBranch->id);
                }
            }
        }

        return $next($request);
    }
}

<?php

namespace Modules\Crm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCrmAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_if(!$user || !$user->can('access_crm'), 403, 'You are not allowed to access CRM.');

        return $next($request);
    }
}

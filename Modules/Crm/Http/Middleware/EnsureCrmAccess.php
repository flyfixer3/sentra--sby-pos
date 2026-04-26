<?php

namespace Modules\Crm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmUserAccessOverride;

class EnsureCrmAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Authentication required.');
        }

        // Per-user override takes priority over role permission
        $override = CrmUserAccessOverride::where('user_id', $user->id)->first();
        if ($override && $override->blocked) {
            abort(403, 'Akses CRM Anda telah dinonaktifkan.');
        }

        // Fall back to role-based permission check
        abort_if(!$user->can('access_crm'), 403, 'You are not allowed to access CRM.');

        return $next($request);
    }
}

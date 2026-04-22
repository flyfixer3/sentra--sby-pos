<?php

namespace Modules\Crm\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResolveBranchFromHeader
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->headers->get('X-Branch-Id');

        if ((!env('CRM_API_NO_AUTH', false)) || (env('CRM_API_NO_AUTH', false) && auth()->check())) {
            if ($header !== null && $header !== '') {
                $key = strtolower(trim((string) $header));
                if ($key === 'all') {
                    if (!Gate::allows('view_all_branches')) {
                        abort(403, 'You are not allowed to use All Branch mode.');
                    }
                    BranchContext::set('all');
                } else {
                    if (!is_numeric($header)) {
                        abort(422, 'Invalid X-Branch-Id header. Use a numeric branch id or "all".');
                    }
                    BranchContext::set((int) $header);
                }
            }
        } else {
            if ($header !== null && $header !== '') {
                $key = strtolower(trim((string) $header));
                if ($key === 'all') {
                    session(['active_branch' => 'all']);
                } else {
                    if (!is_numeric($header)) {
                        abort(422, 'Invalid X-Branch-Id header. Use a numeric branch id or "all".');
                    }
                    session(['active_branch' => (int) $header]);
                }
            }
        }

        $method = strtoupper($request->getMethod());
        $isWrite = in_array($method, ['POST','PUT','PATCH','DELETE'], true);
        if ($isWrite && BranchContext::isAll()) {
            abort(422, "Please select a specific branch first (writes are not allowed in 'All Branch' mode).");
        }

        if ($isWrite && BranchContext::id() === null) {
            abort(422, 'Missing X-Branch-Id header. Provide a numeric branch id for write requests.');
        }

        return $next($request);
    }
}
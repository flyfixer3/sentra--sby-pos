<?php

namespace Modules\Crm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetTestBranchForNoAuth
{
    public function handle(Request $request, Closure $next)
    {
        if ((bool) env('CRM_API_NO_AUTH', false)) {
            $active = session('active_branch');
            if (!$active || $active === 'all') {
                $testBranch = env('CRM_API_TEST_BRANCH_ID');
                if (is_numeric($testBranch) && (int) $testBranch > 0) {
                    // TEMP: local testing only — set numeric branch id directly in session
                    session(['active_branch' => (int) $testBranch]);
                }
            }
        }
        return $next($request);
    }
}
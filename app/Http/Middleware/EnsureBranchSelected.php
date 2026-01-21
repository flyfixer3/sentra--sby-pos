<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        // ✅ ikuti definisi project lama:
        // ALL kalau BranchContext::id() === null
        $isAll = BranchContext::id() === null;

        if ($isAll) {
            // AJAX/API -> jangan redirect
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please select a branch first. This action is not allowed in All Branch mode.'
                ], 422);
            }

            if (function_exists('toast')) {
                toast('Please select a branch first. This action is not allowed in All Branch mode.', 'warning');
            }

            // ✅ redirect ke halaman aman yang READ-ONLY
            // PENTING: pastikan route ini memang tidak pakai branch.selected
            if (Route::has('sales.index')) {
                return redirect()->route('sales.index');
            }

            return redirect('/sales');
        }

        return $next($request);
    }
}

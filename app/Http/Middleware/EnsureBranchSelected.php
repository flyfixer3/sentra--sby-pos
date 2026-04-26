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
            $message = $this->isPrintRoute($request)
                ? 'Please select a specific branch before printing documents.'
                : 'Please select a branch first. This action is not allowed in All Branch mode.';

            // AJAX/API -> jangan redirect
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            if (function_exists('toast')) {
                toast($message, 'warning');
            }

            if ($request->headers->has('referer')) {
                return redirect()->back()->with('error', $message);
            }

            // ✅ redirect ke halaman aman yang READ-ONLY
            // PENTING: pastikan route ini memang tidak pakai branch.selected
            if (Route::has('sales.index')) {
                return redirect()->route('sales.index')->with('error', $message);
            }

            return redirect('/sales')->with('error', $message);
        }

        return $next($request);
    }

    private function isPrintRoute(Request $request): bool
    {
        $routeName = (string) optional($request->route())->getName();
        $path = trim($request->path(), '/');

        foreach (['print', 'pdf', 'receipt', 'barcode'] as $needle) {
            if (strpos($routeName, $needle) !== false || strpos($path, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

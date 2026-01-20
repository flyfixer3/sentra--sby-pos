<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $activeBranch = session('active_branch');

        // ✅ Deteksi mode ALL dari berbagai kemungkinan value
        $isAll = false;

        if ($activeBranch === null) {
            $isAll = true;
        } elseif (is_string($activeBranch) && strtolower(trim($activeBranch)) === 'all') {
            $isAll = true;
        } elseif (is_numeric($activeBranch) && (int) $activeBranch <= 0) {
            $isAll = true;
        } elseif (is_array($activeBranch) && empty($activeBranch)) {
            $isAll = true;
        }

        if ($isAll) {
            // ✅ Pesan yang jelas
            if (function_exists('toast')) {
                toast('Please select a branch first. This action is not allowed in All Branch mode.', 'warning');
            }

            // ✅ Untuk request API/AJAX
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please select a branch first. This action is not allowed in All Branch mode.'
                ], 422);
            }

            // ✅ Redirect balik kalau bisa, fallback ke sales.index
            $fallback = route('sales.index');

            return redirect()->to(url()->previous() ?: $fallback);
        }

        return $next($request);
    }
}

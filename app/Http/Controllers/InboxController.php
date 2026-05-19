<?php

namespace App\Http\Controllers;

use Modules\Adjustment\Entities\Adjustment;

class InboxController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->hasRole('Super Admin');

        $query = Adjustment::query()
            ->with(['branch', 'warehouse', 'creator'])
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');

        if (!$isSuperAdmin) {
            $query->where('submitted_by', optional($user)->id);
        }

        $adjustments = $query->paginate(25);

        return view('inbox.index', compact('adjustments', 'isSuperAdmin'));
    }
}

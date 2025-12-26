<?php

namespace App\Http\Controllers;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SwitchBranchController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'branch_id' => ['required'],
        ]);

        $user = Auth::user();

        // ✅ normalisasi biar aman dari "all ", "ALL", dll
        $branchRaw = trim((string) $request->branch_id);
        $branchKey = strtolower($branchRaw);

        dd([
            'branch_id_raw' => $request->branch_id,
            'branch_id_trim_lower' => strtolower(trim((string)$request->branch_id)),
            'roles' => Auth::user()->getRoleNames(),
            'can_view_all' => Auth::user()->can('view_all_branches'),
        ]);

        // Mode ALL
        if ($branchKey === 'all') {
            // pakai BranchContext biar 1 pintu
            BranchContext::set('all');
            return back();
        }

        // Mode single branch (angka)
        BranchContext::set($branchRaw);

        return back();
    }
}

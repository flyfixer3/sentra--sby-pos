<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

class BranchSwitchController extends Controller
{
    public function switch(Request $request)
    {
        $branchId = $request->input('branch_id');

        // Validasi apakah user memiliki akses ke cabang tersebut
        if (!Auth::user()->allAvailableBranches()->pluck('id')->contains($branchId)) {
            abort(403, 'Unauthorized branch access');
        }

        Session::put('active_branch', $branchId);

        return redirect()->back()->with('success', 'Switched branch successfully.');
    }
}

<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;

class CustomersController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $search = trim((string) $request->query('search', ''));
        $limit = min(max((int) $request->query('limit', 10), 1), 25);
        $branchId = BranchContext::id();

        $rows = Customer::query()
            ->select('id', 'customer_name', 'customer_phone', 'customer_email', 'branch_id')
            ->when($branchId !== null, fn ($query) => $query->forActiveBranch((int) $branchId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_phone', 'like', '%' . $search . '%')
                        ->orWhere('customer_email', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('customer_name')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }
}

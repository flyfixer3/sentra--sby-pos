<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\ServiceOrder;
use Modules\Crm\Entities\Warranty;

class WarrantiesController extends Controller
{
    protected function requireBranch(): int
    {
        $id = BranchContext::id();
        if ($id === null) {
            return abort(422, "Please select a specific branch first (not 'All Branch').");
        }
        return (int) $id;
    }

    protected function currentUserIsTechnicianOnly(): bool
    {
        $user = auth()->user();
        if (!$user || !method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Technician') && Gate::denies('assign_crm_service_orders');
    }

    protected function abortIfTechnicianNotAssigned(ServiceOrder $serviceOrder): void
    {
        if (!$this->currentUserIsTechnicianOnly()) {
            return;
        }

        if (!$serviceOrder->technicians()->where('user_id', auth()->id())->exists()) {
            abort(403, 'You are not assigned to this service order.');
        }
    }

    public function show(int $id)
    {
        abort_if(Gate::denies('show_crm_warranties'), 403);
        $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);
        $this->abortIfTechnicianNotAssigned($so);
        $w = Warranty::where('service_order_id', $so->id)->first();
        if (!$w) abort(404, 'Warranty not found');
        return response()->json($w);
    }

    public function upsert(Request $request, int $id)
    {
        abort_if(Gate::denies('upsert_crm_warranties'), 403);
        $branchId = $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);
        $this->abortIfTechnicianNotAssigned($so);

        if ($so->status !== 'completed') {
            abort(422, 'Warranty can only be issued after job completed.');
        }

        $data = $request->validate([
            'coverage_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:120'],
            'type' => ['nullable', 'string', 'max:50'],
            'start_at' => ['nullable', 'date'],
            'conditions' => ['nullable', 'array'],
        ]);

        $coverageMonths = (int) ($data['coverage_months'] ?? $data['duration'] ?? 0);
        if ($coverageMonths <= 0) {
            abort(422, 'The coverage_months field is required.');
        }

        $start = !empty($data['start_at']) ? Carbon::parse($data['start_at'])->startOfDay() : Carbon::parse($so->completed_at ?? now())->startOfDay();
        $end = (clone $start)->addMonths($coverageMonths);
        $conditions = $data['conditions'] ?? [];
        if (!empty($data['type'])) {
            $conditions['type'] = $data['type'];
        }

        $w = Warranty::firstOrNew(['service_order_id' => $so->id]);
        if (!$w->exists && empty($w->warranty_number)) {
            $w->warranty_number = 'WAR-TMP-' . strtoupper(bin2hex(random_bytes(4)));
        }

        $w->fill([
            'branch_id' => $branchId,
            'coverage_months' => $coverageMonths,
            'start_at' => $start->toDateString(),
            'end_at' => $end->toDateString(),
            'conditions' => $conditions ?: null,
        ]);
        $w->save();

        if (empty($w->warranty_number)) {
            $tmp = 'WAR-' . str_pad((string) $w->id, 6, '0', STR_PAD_LEFT);
            $w->update(['warranty_number' => $tmp]);
        }

        return response()->json($w->fresh());
    }
}

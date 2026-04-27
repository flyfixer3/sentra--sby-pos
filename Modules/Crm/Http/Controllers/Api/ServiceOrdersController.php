<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\ServiceOrder;
use Modules\Crm\Http\Requests\StoreServiceOrderRequest;
use Modules\Crm\Http\Requests\UpdateServiceOrderRequest;

class ServiceOrdersController extends Controller
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

    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_service_orders'), 403);
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        $orders = ServiceOrder::query()
            ->select([
                'id',
                'branch_id',
                'lead_id',
                'customer_id',
                'spk_number',
                'title',
                'description',
                'address_snapshot',
                'map_link_snapshot',
                'install_location_type',
                'status',
                'scheduled_at',
                'departed_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'admin_note',
                'technician_note',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->with([
                'customer',
                'lead' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'customer_id',
                    'assigned_user_id',
                    'status',
                    'source',
                    'contact_name',
                    'contact_phone',
                    'contact_whatsapp',
                    'contact_email',
                    'vehicle_make',
                    'vehicle_model',
                    'vehicle_year',
                    'vehicle_plate',
                    'service_type',
                    'glass_type',
                    'glass_types',
                    'estimated_price',
                    'realized_price',
                    'stock_status',
                    'install_location_type',
                    'install_location',
                    'map_link',
                    'scheduled_at',
                    'conversation_with',
                    'conversation_user_ids',
                    'sales_chat_owner',
                    'sales_owner_user_ids',
                    'next_follow_up_at',
                    'notes',
                    'created_at',
                    'updated_at',
                ]),
                'technicians' => fn ($query) => $query
                    ->select('id', 'service_order_id', 'user_id', 'role', 'status', 'assigned_by', 'assigned_at', 'started_at', 'completed_at', 'note')
                    ->with(['user' => fn ($userQuery) => $userQuery->without('media')->select('id', 'name', 'email')]),
                'photos.media',
                'warranty',
            ]);

        if ($this->currentUserIsTechnicianOnly()) {
            $orders->whereHas('technicians', fn ($query) => $query->where('user_id', auth()->id()));
        }

        $orders = $orders
            ->latest('id')
            ->simplePaginate($limit);

        return response()->json($orders);
    }

    public function store(StoreServiceOrderRequest $request)
    {
        abort_if(Gate::denies('create_crm_service_orders'), 403);
        $branchId = $this->requireBranch();

        $data = $request->validated();

        $tmpSpk = 'SPK-TMP-' . strtoupper(bin2hex(random_bytes(4)));

        $so = ServiceOrder::create(array_merge($data, [
            'branch_id' => $branchId,
            'spk_number' => $tmpSpk,
            'status' => 'scheduled',
        ]));

        if (function_exists('make_reference_id')) {
            $finalSpk = make_reference_id('SPK', (int) $so->id);
        } else {
            $finalSpk = 'SPK-' . str_pad((string) $so->id, 6, '0', STR_PAD_LEFT);
        }
        $so->update(['spk_number' => $finalSpk]);

        if ($so->lead_id) {
            $leadUpdate = [
                'status' => 'terjadwal',
                'scheduled_at' => $so->scheduled_at,
            ];
            if ($so->customer_id) {
                $leadUpdate['customer_id'] = $so->customer_id;
            }
            $so->lead()->update($leadUpdate);
        }

        return response()->json($so->fresh(['customer','lead']), 201);
    }

    public function show(int $id)
    {
        abort_if(Gate::denies('show_crm_service_orders'), 403);
        $so = ServiceOrder::with(['customer','lead','technicians.user','photos.media','warranty'])
            ->findOrFail($id);

        if ($this->currentUserIsTechnicianOnly() && !$so->technicians->contains('user_id', auth()->id())) {
            abort(403, 'You are not assigned to this service order.');
        }

        return response()->json($so);
    }

    public function update(UpdateServiceOrderRequest $request, int $id)
    {
        abort_if(Gate::denies('edit_crm_service_orders'), 403);
        $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);
        $data = $request->validated();

        if (($data['status'] ?? null) === 'completed' && empty($data['completed_at']) && Schema::hasColumn('crm_service_orders', 'completed_at')) {
            $data['completed_at'] = now();
        }

        if (($data['status'] ?? null) === 'cancelled' && empty($data['cancelled_at']) && Schema::hasColumn('crm_service_orders', 'cancelled_at')) {
            $data['cancelled_at'] = now();
        }

        $so->update($data);

        if (isset($data['status']) && $so->lead_id) {
            if ($data['status'] === 'cancelled') {
                $so->lead()->update(['status' => 'batal']);
            } elseif ($data['status'] === 'scheduled') {
                $so->lead()->update(['status' => 'terjadwal']);
            } elseif ($data['status'] === 'in_progress') {
                $so->lead()->update(['status' => 'dalam_pengerjaan']);
            } elseif ($data['status'] === 'completed') {
                $so->lead()->update(['status' => 'selesai']);
            }
        }

        return response()->json($so->fresh(['customer','lead','technicians.user','photos.media','warranty']));
    }

    public function destroy(int $id)
    {
        abort_if(Gate::denies('delete_crm_service_orders'), 403);
        return response()->json(['message' => 'Delete not implemented in Phase-1 minimal scope'], 501);
    }
}

<?php
namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Crm\Entities\Lead;
use Modules\Crm\Entities\CrmNotification;
use Modules\Crm\Entities\CtaClick;
use Modules\Crm\Entities\ServiceOrder;
use Modules\Product\Entities\Product;

class LeadsController extends Controller
{
    protected $statuses = [
        'prospek',
        'prospek_baru',
        'negosiasi',
        'follow_up',
        'deal',
        'terjadwal',
        'dalam_pengerjaan',
        'pending_pengerjaan',
        'selesai',
        'batal',
    ];

    protected function requireBranch(): int
    {
        $id = BranchContext::id();
        if ($id === null) {
            return abort(422, "Please select a specific branch first (not 'All Branch').");
        }
        return (int) $id;
    }

    protected function leadRelations(): array
    {
        return [
            'product' => fn ($query) => $query->without('media')->select('id', 'product_code', 'product_name', 'product_price', 'product_unit'),
            'leadProducts' => fn ($query) => $query->orderBy('id'),
            'assignedUser' => fn ($query) => $query->without('media')->select('id', 'name', 'email'),
            'assignees' => fn ($query) => $query->without('media')->select('users.id', 'users.name', 'users.email'),
        ];
    }

    protected function findLeadForMutation(int $id): Lead
    {
        return Lead::withoutGlobalScopes()->findOrFail($id);
    }

    protected function reloadLead(Lead $lead): Lead
    {
        return Lead::withoutGlobalScopes()
            ->with($this->leadRelations())
            ->findOrFail($lead->id);
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);
        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $source = trim((string) $request->query('source', ''));
        $refCode = trim((string) $request->query('ref_code', ''));

        $leads = Lead::query()
            ->select([
                'id',
                'branch_id',
                'customer_id',
                'product_id',
                'product_code',
                'product_name',
                'sale_order_id',
                'install_location_type',
                'assigned_user_id',
                'status',
                'source',
                'ref_code',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'gclid',
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
            ])
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->when($refCode !== '', fn ($query) => $query->where('ref_code', 'like', '%' . $refCode . '%'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('contact_name', 'like', '%' . $search . '%')
                        ->orWhere('contact_whatsapp', 'like', '%' . $search . '%')
                        ->orWhere('vehicle_make', 'like', '%' . $search . '%')
                        ->orWhere('vehicle_model', 'like', '%' . $search . '%')
                        ->orWhere('source', 'like', '%' . $search . '%')
                        ->orWhere('ref_code', 'like', '%' . $search . '%');
                });
            })
            ->addSelect([
                'service_order_id' => ServiceOrder::query()
                    ->select('id')
                    ->whereColumn('lead_id', 'crm_leads.id')
                    ->latest('id')
                    ->limit(1),
            ])
            ->with([
                'product' => fn ($query) => $query->without('media')->select('id', 'product_code', 'product_name', 'product_price', 'product_unit'),
                'leadProducts' => fn ($query) => $query->orderBy('id'),
                'assignedUser' => fn ($query) => $query->without('media')->select('id', 'name', 'email'),
                'assignees' => fn ($query) => $query->without('media')->select('users.id', 'users.name', 'users.email'),
            ])
            ->latest('id')
            ->simplePaginate($limit);
        return response()->json($leads);
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_crm_leads'), 403);
        $branchId = $this->requireBranch();

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'cta_click_id' => ['nullable', 'integer', 'exists:crm_cta_clicks,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'lead_products' => ['nullable', 'array'],
            'lead_products.*.product_id' => ['required_with:lead_products', 'integer', 'exists:products,id'],
            'lead_products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lead_products.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in($this->statuses)],
            'source' => ['nullable', 'string', 'max:50'],
            'ref_code' => ['nullable', 'string', 'max:50'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'utm_content' => ['nullable', 'string', 'max:150'],
            'gclid' => ['nullable', 'string', 'max:150'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'vehicle_make' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_year' => ['nullable', 'string', 'max:20'],
            'vehicle_plate' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'glass_type' => ['nullable', 'string', 'max:100'],
            'glass_types' => ['nullable', 'array'],
            'glass_types.*' => ['string', 'max:100'],
            'estimated_price' => ['nullable', 'integer', 'min:0'],
            'realized_price' => ['nullable', 'integer', 'min:0'],
            'stock_status' => ['nullable', 'string', 'max:50'],
            'install_location_type' => ['nullable', Rule::in(['workshop', 'customer_home'])],
            'install_location' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'map_link' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'conversation_with' => ['nullable', 'string', 'max:255'],
            'conversation_user_ids' => ['nullable', 'array'],
            'conversation_user_ids.*' => ['integer', 'exists:users,id'],
            'sales_chat_owner' => ['nullable', 'string', 'max:255'],
            'sales_owner_user_ids' => ['nullable', 'array'],
            'sales_owner_user_ids.*' => ['integer', 'exists:users,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['install_location_type'] ?? 'workshop') === 'customer_home') {
            if (empty($data['install_location']) || empty($data['map_link'])) {
                abort(422, 'Alamat pemasangan dan link Maps wajib untuk layanan di rumah konsumen.');
            }
        }

        $leadProducts = $data['lead_products'] ?? null;
        unset($data['lead_products']);

        $data = $this->normalizeLeadPayload($data, $leadProducts, $branchId);
        $assignedUserIds = $this->extractAssignedUserIds($data);
        $ctaClickId = $data['cta_click_id'] ?? null;
        unset($data['cta_click_id']);

        $lead = DB::transaction(function () use ($data, $leadProducts, $assignedUserIds, $ctaClickId, $branchId) {
            $lead = Lead::create(array_merge($data, ['branch_id' => $branchId]));
            $this->syncLeadProducts($lead, $leadProducts, $branchId);
            $this->syncAssignees($lead, $assignedUserIds, $branchId);
            $this->attachCtaClick($lead, $ctaClickId);
            return $lead;
        });

        $this->notifyLeadUsers($lead, $assignedUserIds, $data['sales_owner_user_ids'] ?? null, 'assigned');

        if (function_exists('activity')) {
            activity()->performedOn($lead)
                ->causedBy(auth()->user())
                ->withProperties(['action' => 'create'])
                ->log('lead_created');
        }

        return response()->json($this->reloadLead($lead), 201);
    }

    public function show(int $id)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);
        $lead = Lead::withoutGlobalScopes()
            ->addSelect([
                'crm_leads.*',
                'service_order_id' => ServiceOrder::query()
                    ->select('id')
                    ->whereColumn('lead_id', 'crm_leads.id')
                    ->latest('id')
                    ->limit(1),
            ])
            ->with(array_merge(['customer'], $this->leadRelations()))
            ->findOrFail($id);
        return response()->json($lead);
    }

    public function update(Request $request, int $id)
    {
        abort_if(Gate::denies('edit_crm_leads'), 403);
        $requestedBranchId = $this->requireBranch();
        $lead = $this->findLeadForMutation($id);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'cta_click_id' => ['nullable', 'integer', 'exists:crm_cta_clicks,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'lead_products' => ['nullable', 'array'],
            'lead_products.*.product_id' => ['required_with:lead_products', 'integer', 'exists:products,id'],
            'lead_products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lead_products.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'sale_order_id' => ['nullable', 'integer', 'exists:sale_orders,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in($this->statuses)],
            'source' => ['nullable', 'string', 'max:50'],
            'ref_code' => ['nullable', 'string', 'max:50'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'utm_content' => ['nullable', 'string', 'max:150'],
            'gclid' => ['nullable', 'string', 'max:150'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'vehicle_make' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_year' => ['nullable', 'string', 'max:20'],
            'vehicle_plate' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'glass_type' => ['nullable', 'string', 'max:100'],
            'glass_types' => ['nullable', 'array'],
            'glass_types.*' => ['string', 'max:100'],
            'estimated_price' => ['nullable', 'integer', 'min:0'],
            'realized_price' => ['nullable', 'integer', 'min:0'],
            'stock_status' => ['nullable', 'string', 'max:50'],
            'install_location_type' => ['nullable', Rule::in(['workshop', 'customer_home'])],
            'install_location' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'map_link' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'conversation_with' => ['nullable', 'string', 'max:255'],
            'conversation_user_ids' => ['nullable', 'array'],
            'conversation_user_ids.*' => ['integer', 'exists:users,id'],
            'sales_chat_owner' => ['nullable', 'string', 'max:255'],
            'sales_owner_user_ids' => ['nullable', 'array'],
            'sales_owner_user_ids.*' => ['integer', 'exists:users,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['install_location_type'] ?? null) === 'customer_home') {
            if (empty($data['install_location']) || empty($data['map_link'])) {
                abort(422, 'Alamat pemasangan dan link Maps wajib untuk layanan di rumah konsumen.');
            }
        }

        $leadProductsProvided = array_key_exists('lead_products', $data);
        $leadProducts = $leadProductsProvided ? $data['lead_products'] : null;
        unset($data['lead_products']);

        $ctaClickId = $data['cta_click_id'] ?? null;
        unset($data['cta_click_id']);

        $targetBranchId = (int) ($data['branch_id'] ?? $lead->branch_id ?? $requestedBranchId);
        if (!$leadProductsProvided && array_key_exists('branch_id', $data) && $targetBranchId !== (int) $lead->branch_id) {
            $leadProducts = $lead->leadProducts()
                ->get(['product_id', 'quantity', 'unit_price'])
                ->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (int) $item->unit_price,
                ])
                ->all();
        }

        $data = $this->normalizeLeadPayload($data, $leadProducts, $targetBranchId);
        $assignedUserIds = $this->extractAssignedUserIds($data);
        $oldStatus = $lead->status;
        $lead->update($data);
        $this->syncLeadProducts($lead, $leadProducts, $targetBranchId);
        if ($assignedUserIds !== null) {
            $this->syncAssignees($lead, $assignedUserIds, $targetBranchId);
        }
        $this->notifyLeadUsers($lead, $assignedUserIds, $data['sales_owner_user_ids'] ?? null, 'updated');
        $this->attachCtaClick($lead, $ctaClickId);

        if (function_exists('activity')) {
            $props = ['action' => 'update'];
            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $props['status_from'] = $oldStatus;
                $props['status_to'] = $data['status'];
            }
            activity()->performedOn($lead)
                ->causedBy(auth()->user())
                ->withProperties($props)
                ->log('lead_updated');
        }

        return response()->json($this->reloadLead($lead));
    }

    public function salesUrl(int $id)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);
        $branchId = $this->requireBranch();

        $lead = Lead::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->findOrFail($id);

        if ($lead->sale_order_id) {
            return response()->json([
                'url' => url('/sale-orders/' . $lead->sale_order_id),
            ]);
        }

        return response()->json([
            'url' => url('/sale-orders/create?' . http_build_query([
                'source' => 'lead',
                'lead_id' => $lead->id,
                'branch_id' => $lead->branch_id,
            ])),
        ]);
    }

    public function convert(Request $request, int $id)
    {
        abort_if(Gate::denies('convert_crm_leads'), 403);
        $branchId = $this->requireBranch();
        $lead = $this->findLeadForMutation($id);

        $payload = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address_snapshot' => ['required', 'string', 'max:500'],
            'map_link_snapshot' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $so = DB::transaction(function () use ($branchId, $lead, $payload) {
            $tmpSpk = 'SPK-TMP-' . strtoupper(bin2hex(random_bytes(4)));

            $so = ServiceOrder::create([
                'branch_id' => $branchId,
                'lead_id' => $lead->id,
                'customer_id' => (int) $payload['customer_id'],
                'spk_number' => $tmpSpk,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'address_snapshot' => $payload['address_snapshot'],
                'map_link_snapshot' => $payload['map_link_snapshot'] ?? null,
                'status' => 'scheduled',
                'scheduled_at' => $payload['scheduled_at'] ?? null,
            ]);

            $finalSpk = function_exists('make_reference_id')
                ? make_reference_id('SPK', (int) $so->id)
                : 'SPK-' . str_pad((string) $so->id, 6, '0', STR_PAD_LEFT);

            $so->update(['spk_number' => $finalSpk]);
            $lead->update(['status' => 'terjadwal', 'customer_id' => $payload['customer_id']]);

            return $so;
        });

        if (function_exists('activity')) {
            activity()->performedOn($lead)
                ->causedBy(auth()->user())
                ->withProperties(['action' => 'convert', 'service_order_id' => $so->id])
                ->log('lead_converted');
        }

        return response()->json($so->fresh(['customer','lead']), 201);
    }

    public function destroy(int $id)
    {
        abort_if(Gate::denies('delete_crm_leads'), 403);
        $this->requireBranch();
        $lead = $this->findLeadForMutation($id);
        $lead->delete();
        return response()->json(['message' => 'Deleted']);
    }

    protected function normalizeLeadPayload(array $data, ?array $leadProducts = null, ?int $branchId = null): array
    {
        $preparedProducts = $leadProducts !== null ? $this->prepareLeadProducts($leadProducts, $branchId) : [];

        if (!empty($preparedProducts)) {
            $first = $preparedProducts[0];
            $data['product_id'] = $first['product_id'];
            $data['product_code'] = $first['product_code'];
            $data['product_name'] = $first['product_name'];

            if (empty($data['estimated_price'])) {
                $data['estimated_price'] = collect($preparedProducts)->sum('total_price');
            }

            $statuses = collect($preparedProducts)->pluck('stock_status')->filter()->unique()->values();
            $data['stock_status'] = $statuses->count() === 1 && $statuses[0] === 'tersedia'
                ? 'tersedia'
                : ($statuses->contains('perlu_order') ? 'perlu_order' : 'tidak_tersedia');
        } elseif (!empty($data['product_id'])) {
            $product = Product::without('media')->find((int) $data['product_id']);
            if ($product) {
                $data['product_code'] = $product->product_code;
                $data['product_name'] = $product->product_name;

                if (empty($data['estimated_price'])) {
                    $data['estimated_price'] = (int) ($product->product_price ?? 0);
                }

                $data['stock_status'] = $this->stockStatusForProduct((int) $product->id, $branchId);
            }
        } elseif ($leadProducts !== null) {
            $data['product_id'] = null;
            $data['product_code'] = null;
            $data['product_name'] = null;
            $data['estimated_price'] = null;
            $data['stock_status'] = null;
        }

        if (isset($data['address']) && !isset($data['install_location'])) {
            $data['install_location'] = $data['address'];
        }

        unset($data['address']);

        $locationType = $data['install_location_type'] ?? 'workshop';
        if ($locationType === 'workshop') {
            $data['install_location'] = null;
            $data['map_link'] = null;
        }

        if (!isset($data['scheduled_at']) && !empty($data['scheduled_date'])) {
            $time = $data['scheduled_time'] ?? '00:00';
            $data['scheduled_at'] = trim($data['scheduled_date'] . ' ' . $time);
        }

        unset($data['scheduled_date'], $data['scheduled_time']);

        if (array_key_exists('glass_types', $data)) {
            $data['glass_types'] = collect($data['glass_types'] ?? [])
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn ($value) => trim($value))
                ->unique()
                ->values()
                ->all();

            if (!empty($data['glass_types']) && empty($data['glass_type'])) {
                $data['glass_type'] = $data['glass_types'][0];
            }
        }

        foreach (['conversation_user_ids', 'sales_owner_user_ids'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = collect($data[$field] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $data;
    }

    protected function stockStatusForProduct(int $productId, ?int $branchId = null): string
    {
        return $this->stockSnapshotForProduct($productId, $branchId)['stock_status'];
    }

    protected function stockSnapshotForProduct(int $productId, ?int $branchId = null): array
    {
        $branchId = $branchId ?? BranchContext::id();
        if ($branchId === null) {
            return [
                'total_qty' => 0,
                'reserved_qty' => 0,
                'incoming_qty' => 0,
                'available_qty' => 0,
                'stock_status' => 'tidak_tersedia',
            ];
        }

        $stock = DB::table('stocks')
            ->where('product_id', $productId)
            ->where('branch_id', (int) $branchId)
            ->selectRaw('COALESCE(SUM(qty_total),0) as total_qty, COALESCE(SUM(qty_reserved),0) as reserved_qty, COALESCE(SUM(qty_incoming),0) as incoming_qty')
            ->first();

        $available = max((int) ($stock->total_qty ?? 0) - (int) ($stock->reserved_qty ?? 0), 0);
        if ($available > 0) {
            $status = 'tersedia';
        } else {
            $status = (int) ($stock->incoming_qty ?? 0) > 0 ? 'perlu_order' : 'tidak_tersedia';
        }

        return [
            'total_qty' => (int) ($stock->total_qty ?? 0),
            'reserved_qty' => (int) ($stock->reserved_qty ?? 0),
            'incoming_qty' => (int) ($stock->incoming_qty ?? 0),
            'available_qty' => $available,
            'stock_status' => $status,
        ];
    }

    protected function prepareLeadProducts(array $items, ?int $branchId = null): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $products = Product::without('media')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        return collect($items)
            ->filter(fn ($item) => !empty($item['product_id']) && $products->has((int) $item['product_id']))
            ->unique(fn ($item) => (int) $item['product_id'])
            ->map(function ($item) use ($products, $branchId) {
                $product = $products[(int) $item['product_id']];
                $quantity = max((int) ($item['quantity'] ?? 1), 1);
                $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                    ? (int) $item['unit_price']
                    : (int) ($product->product_price ?? 0);
                $stock = $this->stockSnapshotForProduct((int) $product->id, $branchId);

                return [
                    'product_id' => (int) $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'available_qty' => $stock['available_qty'],
                    'incoming_qty' => $stock['incoming_qty'],
                    'stock_status' => $stock['stock_status'],
                ];
            })
            ->values()
            ->all();
    }

    protected function syncLeadProducts(Lead $lead, ?array $items, ?int $branchId = null): void
    {
        if ($items === null) {
            return;
        }

        $prepared = $this->prepareLeadProducts($items, $branchId);
        $lead->leadProducts()->delete();

        foreach ($prepared as $item) {
            $lead->leadProducts()->create($item);
        }
    }

    protected function extractAssignedUserIds(array &$data): ?array
    {
        if (!array_key_exists('assigned_user_ids', $data)) {
            return null;
        }

        $ids = collect($data['assigned_user_ids'])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        unset($data['assigned_user_ids']);

        if (!empty($ids) && empty($data['assigned_user_id'])) {
            $data['assigned_user_id'] = $ids[0];
        }

        return $ids;
    }

    protected function syncAssignees(Lead $lead, ?array $assignedUserIds, int $branchId): void
    {
        if ($assignedUserIds === null) {
            return;
        }

        $sync = [];
        foreach ($assignedUserIds as $userId) {
            $sync[(int) $userId] = [
                'branch_id' => $branchId,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ];
        }

        $lead->assignees()->sync($sync);
    }

    protected function notifyLeadUsers(Lead $lead, ?array $assignedUserIds, ?array $salesOwnerUserIds, string $action): void
    {
        $ids = collect($assignedUserIds ?? [])
            ->merge($salesOwnerUserIds ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) auth()->id())
            ->values();

        foreach ($ids as $userId) {
            CrmNotification::create([
                'branch_id' => (int) $lead->branch_id,
                'user_id' => $userId,
                'lead_id' => (int) $lead->id,
                'type' => 'lead_' . $action,
                'title' => $action === 'assigned' ? 'Lead ditugaskan' : 'Lead diperbarui',
                'message' => trim(($lead->contact_name ?: 'Lead') . ' membutuhkan perhatian Anda.'),
                'data' => [
                    'lead_id' => (int) $lead->id,
                    'contact_name' => $lead->contact_name,
                ],
            ]);
        }
    }

    protected function attachCtaClick(Lead $lead, $ctaClickId): void
    {
        if (empty($ctaClickId)) {
            return;
        }

        CtaClick::query()
            ->where('id', (int) $ctaClickId)
            ->update(['lead_id' => (int) $lead->id]);
    }
}

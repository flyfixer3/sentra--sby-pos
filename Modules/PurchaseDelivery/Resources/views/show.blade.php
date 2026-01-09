@extends('layouts.app')

@section('title', "Purchase Delivery #{$purchaseDelivery->id}")

@push('page_css')
<style>
    .pd-wrap .pd-title{
        font-weight:800;
        font-size:18px;
        margin:0;
        display:flex;
        gap:10px;
        align-items:center;
        flex-wrap:wrap;
    }
    .pd-wrap .pd-sub{
        font-size:12px;
        color:#6c757d;
        margin:2px 0 0;
    }

    .pd-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:5px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:700;
        border:1px solid rgba(0,0,0,.08);
        background:rgba(0,0,0,.03);
        color:#343a40;
        white-space:nowrap;
        text-transform:uppercase;
    }
    .pd-badge--pending{ background:rgba(255,193,7,.12); color:#8a6d00; border-color:rgba(255,193,7,.22); }
    .pd-badge--received{ background:rgba(40,167,69,.10); color:#1e7e34; border-color:rgba(40,167,69,.22); }
    .pd-badge--partial{ background:rgba(23,162,184,.12); color:#117a8b; border-color:rgba(23,162,184,.22); }

    .pd-box{
        border:1px solid rgba(0,0,0,.08);
        border-radius:12px;
        padding:14px 14px;
        background:#fff;
    }
    .pd-label{
        font-size:12px;
        color:#6c757d;
        margin-bottom:4px;
    }
    .pd-value{
        font-weight:700;
        color:#212529;
        word-break:break-word;
    }
    .pd-link{
        color:#0d6efd;
        text-decoration:none;
    }
    .pd-link:hover{ text-decoration:underline; }

    .pd-table thead th{
        background:rgba(0,0,0,.02);
        border-bottom:1px solid rgba(0,0,0,.08);
        font-weight:800;
        color:#343a40;
        font-size:13px;
        white-space:nowrap;
    }
    .pd-table tbody td{
        vertical-align:middle;
        font-size:13px;
    }
    .pd-code{
        display:inline-flex;
        padding:3px 8px;
        border-radius:999px;
        font-size:11px;
        font-weight:800;
        border:1px solid rgba(25,135,84,.25);
        background:rgba(25,135,84,.10);
        color:#146c43;
    }

    .pd-footer{
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:center;
        margin-top:16px;
        padding-top:12px;
        border-top:1px dashed rgba(0,0,0,.12);
        flex-wrap:wrap;
    }
    .pd-actions .btn{
        border-radius:10px;
        font-weight:700;
    }

    .pd-pill{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        border:1px solid rgba(0,0,0,.08);
        background:rgba(0,0,0,.02);
        color:#212529;
        white-space:nowrap;
    }
    .pd-pill--ok{ background:rgba(40,167,69,.10); border-color:rgba(40,167,69,.20); color:#1e7e34; }
    .pd-pill--warn{ background:rgba(255,193,7,.12); border-color:rgba(255,193,7,.20); color:#8a6d00; }
    .pd-pill--danger{ background:rgba(220,53,69,.10); border-color:rgba(220,53,69,.20); color:#b02a37; }
    .pd-pill--info{ background:rgba(23,162,184,.12); border-color:rgba(23,162,184,.20); color:#117a8b; }

    .btn-details{
        background:#fff;
        border:1px solid #dbeafe;
        color:#1d4ed8;
        border-radius:999px;
        padding:6px 12px;
        font-weight:800;
        font-size:12px;
        line-height:1;
        display:inline-flex;
        align-items:center;
        gap:8px;
        white-space:nowrap;
    }
    .btn-details:hover{ background:#eff6ff; border-color:#93c5fd; }

    .badge-defect{ background:#2563eb; color:#fff; font-weight:900; padding:5px 8px; border-radius:999px; font-size:11px; }
    .badge-damaged{ background:#ef4444; color:#fff; font-weight:900; padding:5px 8px; border-radius:999px; font-size:11px; }

    .pd-thumb{
        width:44px;
        height:44px;
        border-radius:10px;
        object-fit:cover;
        border:1px solid rgba(0,0,0,.10);
        background:rgba(0,0,0,.02);
    }
    .pd-thumb-wrap{
        display:flex;
        align-items:center;
        gap:8px;
    }
    .pd-muted{ color:#6c757d; font-size:12px; }

    /* Per-unit wrapper row */
    .perunit-row td{
        background:#f1f5f9;
    }
    .perunit-card{
        background:#fff;
        border:1px solid rgba(0,0,0,.10);
        border-radius:12px;
        padding:14px;
    }
    .perunit-title{
        font-weight:900;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
    }
    .perunit-sub{
        font-size:12px;
        color:#6c757d;
        margin-top:4px;
    }
</style>
@endpush

@section('content')
@php
    $rawStatus = strtolower(trim((string)($purchaseDelivery->getRawOriginal('status') ?? $purchaseDelivery->status ?? 'pending')));

    $badgeClass = 'pd-badge pd-badge--pending';
    if ($rawStatus === 'received') $badgeClass = 'pd-badge pd-badge--received';
    if ($rawStatus === 'partial')  $badgeClass = 'pd-badge pd-badge--partial';

    $po        = $purchaseDelivery->purchaseOrder;
    $warehouse = $purchaseDelivery->warehouse;

    // ✅ branch fallback: dari PD dulu, kalau null baru dari PO, kalau masih null fallback dari warehouse->branch
    $branch = $purchaseDelivery->branch
        ?? $po?->branch
        ?? $warehouse?->branch;

    // supplier tetap boleh, tapi vendor display sudah pakai $vendorName dari controller
    $supplier = $po?->supplier;

    $details = $purchaseDelivery->purchaseDeliveryDetails ?? collect();

    $totalExpected = $details->sum(fn($d) => (int)($d->quantity ?? 0));
    $totalReceived = $details->sum(fn($d) => (int)($d->qty_received ?? 0));
    $totalDefect   = $details->sum(fn($d) => (int)($d->qty_defect ?? 0));
    $totalDamaged  = $details->sum(fn($d) => (int)($d->qty_damaged ?? 0));
    $totalConfirmed = $totalReceived + $totalDefect + $totalDamaged;

    $isPending = in_array($rawStatus, ['pending','open'], true);

    // Group per product untuk per-unit view
    $defectsByProduct = ($defects ?? collect())->groupBy('product_id');
    $damagedByProduct = ($damaged ?? collect())->groupBy('product_id');

    // audit helper
    $createdByName = optional($purchaseDelivery->creator)->name ?? optional($purchaseDelivery->creator)->username ?? '-';
    $shipToAddress =
    $po?->branch?->getRawOriginal('address')
    ?? $purchaseDelivery->branch?->getRawOriginal('address')
    ?? $warehouse?->branch?->getRawOriginal('address')
    ?? '-';

    $hasInvoice = $purchaseDelivery->relationLoaded('purchase')
    ? !empty($purchaseDelivery->purchase)
    : $purchaseDelivery->purchase()->exists();

@endphp

<div class="container-fluid pd-wrap">

    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between">
            <div>
                <div class="pd-title">
                    <span>Purchase Delivery #{{ $purchaseDelivery->id }}</span>
                    <span class="{{ $badgeClass }}">{{ $rawStatus }}</span>
                </div>
                <div class="pd-sub">
                    Created by: <strong>{{ $createdByName }}</strong>
                    • Last update: {{ $purchaseDelivery->updated_at ? \Carbon\Carbon::parse($purchaseDelivery->updated_at)->format('d M Y, H:i') : '-' }}
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                {{-- kalau nanti ada route jurnal, ganti href ini --}}
                <a href="javascript:void(0)" class="pd-link">View journal entry</a>
            </div>
        </div>

        <div class="card-body">
            @include('utils.alerts')

            {{-- SUMMARY --}}
            <div class="pd-box d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div style="font-weight:900;">Delivery Summary</div>
                    <div class="pd-sub">
                        Expected: <strong>{{ (int)$totalExpected }}</strong>
                        • Confirmed: <strong>{{ (int)$totalConfirmed }}</strong>
                        @if($isPending)
                            • <span class="pd-muted">Not confirmed yet</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <span class="pd-pill">Expected: {{ (int)$totalExpected }}</span>
                    <span class="pd-pill pd-pill--ok">Received: {{ (int)$totalReceived }}</span>
                    <span class="pd-pill pd-pill--warn">Defect: {{ (int)$totalDefect }}</span>
                    <span class="pd-pill pd-pill--danger">Damaged: {{ (int)$totalDamaged }}</span>
                </div>
            </div>

            {{-- INFO GRID --}}
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="pd-box h-100">
                        <div class="pd-label">Vendor</div>
                        <div class="pd-value">{{  $vendorName  ?? '-' }}</div>

                        <div class="mt-3 pd-label">Email</div>
                        <div class="pd-value" style="font-weight:600;">{{ $vendorEmail ?? '-' }}</div>

                        <div class="mt-3 pd-label">Ship-to (Delivery Destination)</div>
                        <div class="pd-value" style="font-weight:600; white-space:pre-line;">
                            {{ $shipToAddress }}
                        </div>
                        <div class="pd-sub mt-1">Destination address (branch address).</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="pd-box h-100">
                        <div class="pd-label">Shipping Date</div>
                        <div class="pd-value">{{ $purchaseDelivery->date ? \Carbon\Carbon::parse($purchaseDelivery->date)->format('d/m/Y') : '-' }}</div>

                        <div class="mt-3 pd-label">Ship Via</div>
                        <div class="pd-value" style="font-weight:600;">{{ $purchaseDelivery->ship_via ?? '-' }}</div>

                        <div class="mt-3 pd-label">Tracking No.</div>
                        <div class="pd-value" style="font-weight:600;">{{ $purchaseDelivery->tracking_number ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="pd-box h-100">
                        <div class="pd-label">Purchase Order</div>
                        <div class="pd-value">
                            @if($po)
                                <a class="pd-link" href="{{ route('purchase-orders.show', $po->id) }}">
                                    {{ $po->reference ?? ('PO #' . $po->id) }}
                                </a>
                            @else
                                -
                            @endif
                        </div>

                        <div class="mt-3 pd-label">Branch</div>
                        <div class="pd-value" style="font-weight:600;">{{ $branch?->name ?? '-' }}</div>

                        <div class="mt-3 pd-label">Warehouse</div>
                        <div class="pd-value" style="font-weight:600;">{{ $warehouse?->warehouse_name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- ITEMS --}}
            <div class="mt-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div style="font-weight:900;">Items</div>
                    <div class="pd-sub">Total items: {{ $details->count() }}</div>
                </div>

                <div class="table-responsive">
                    <table class="table pd-table table-bordered mb-0">
                        <thead>
                        <tr>
                            <th style="min-width:320px;">Product</th>
                            <th>Description</th>
                            <th class="text-center" style="width:90px;">Expected</th>
                            <th class="text-center" style="width:90px;">Received</th>
                            <th class="text-center" style="width:80px;">Defect</th>
                            <th class="text-center" style="width:90px;">Damaged</th>
                            <th class="text-center" style="width:120px;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($details as $i => $detail)
                            @php
                                $pid = (int)($detail->product_id ?? 0);
                                $expected = (int)($detail->quantity ?? 0);
                                $received = (int)($detail->qty_received ?? 0);
                                $defect   = (int)($detail->qty_defect ?? 0);
                                $damagedQ = (int)($detail->qty_damaged ?? 0);

                                $hasAny = ($defect + $damagedQ) > 0;

                                $defList = $defectsByProduct->get($pid, collect());
                                $dmList  = $damagedByProduct->get($pid, collect());

                                $rowKey = "pd-item-{$i}";
                            @endphp

                            <tr>
                                <td>
                                    <div style="font-weight:800;">{{ $detail->product_name ?? '-' }}</div>
                                    @if(!empty($detail->product_code))
                                        <span class="pd-code">{{ $detail->product_code }}</span>
                                    @endif
                                </td>

                                <td>{{ $detail->description ?: '-' }}</td>

                                <td class="text-center">{{ $expected }}</td>
                                <td class="text-center">
                                    <span class="pd-pill pd-pill--ok" style="padding:4px 10px;">{{ $received }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="pd-pill pd-pill--warn" style="padding:4px 10px;">{{ $defect }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="pd-pill pd-pill--danger" style="padding:4px 10px;">{{ $damagedQ }}</span>
                                </td>

                                <td class="text-center">
                                    @if($hasAny)
                                        <button type="button"
                                                class="btn-details"
                                                data-toggle-perunit="{{ $rowKey }}">
                                            Details
                                            @if($defect > 0)
                                                <span class="badge-defect">{{ $defect }}</span>
                                            @endif
                                            @if($damagedQ > 0)
                                                <span class="badge-damaged">{{ $damagedQ }}</span>
                                            @endif
                                        </button>
                                    @else
                                        <span class="pd-muted">-</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- PER-UNIT EXPAND --}}
                            @if($hasAny)
                                <tr class="perunit-row" id="{{ $rowKey }}" style="display:none;">
                                    <td colspan="7" class="p-3">
                                        <div class="perunit-card">
                                            <div class="perunit-title">
                                                <div>
                                                    Per-Unit Details — <span style="font-weight:900;">{{ $detail->product_name ?? '-' }}</span>
                                                    @if(!empty($detail->product_code))
                                                        <span class="pd-code ms-2">{{ $detail->product_code }}</span>
                                                    @endif
                                                </div>

                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-close-perunit="{{ $rowKey }}">
                                                    Close
                                                </button>
                                            </div>
                                            <div class="perunit-sub">
                                                Ini menampilkan baris per unit (qty = 1) sesuai data yang kamu input saat confirm.
                                            </div>

                                            <div class="row mt-3">
                                                {{-- DEFECT --}}
                                                <div class="col-lg-6">
                                                    <div class="fw-bold mb-2" style="color:#1d4ed8;">
                                                        Defect Items ({{ $defList->count() }})
                                                    </div>

                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="table-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th style="width:170px;">Type</th>
                                                                <th>Description</th>
                                                                <th style="width:140px;" class="text-center">Photo</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            @if($defList->isEmpty())
                                                                <tr>
                                                                    <td colspan="4" class="text-center text-muted py-3">No defect items.</td>
                                                                </tr>
                                                            @else
                                                                @foreach($defList as $k => $d)
                                                                    <tr>
                                                                        <td class="text-center align-middle">{{ $k + 1 }}</td>
                                                                        <td class="align-middle">{{ $d->defect_type ?? '-' }}</td>
                                                                        <td class="align-middle">{{ $d->description ?? '-' }}</td>
                                                                        <td class="text-center align-middle">
                                                                            @if(!empty($d->photo_path))
                                                                                <a href="{{ asset('storage/'.$d->photo_path) }}" target="_blank" class="pd-link">
                                                                                    <img src="{{ asset('storage/'.$d->photo_path) }}" class="pd-thumb" alt="defect">
                                                                                </a>
                                                                            @else
                                                                                <span class="pd-muted">-</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            @endif
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                {{-- DAMAGED --}}
                                                <div class="col-lg-6 mt-3 mt-lg-0">
                                                    <div class="fw-bold mb-2" style="color:#b91c1c;">
                                                        Damaged Items ({{ $dmList->count() }})
                                                    </div>

                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="table-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th>Reason</th>
                                                                <th style="width:90px;" class="text-center">IN</th>
                                                                <th style="width:90px;" class="text-center">OUT</th>
                                                                <th style="width:140px;" class="text-center">Photo</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            @if($dmList->isEmpty())
                                                                <tr>
                                                                    <td colspan="5" class="text-center text-muted py-3">No damaged items.</td>
                                                                </tr>
                                                            @else
                                                                @foreach($dmList as $k => $dm)
                                                                    <tr>
                                                                        <td class="text-center align-middle">{{ $k + 1 }}</td>
                                                                        <td class="align-middle">{{ $dm->reason ?? '-' }}</td>
                                                                        <td class="text-center align-middle">
                                                                            {{ !empty($dm->mutation_in_id) ? '#'.$dm->mutation_in_id : '-' }}
                                                                        </td>
                                                                        <td class="text-center align-middle">
                                                                            {{ !empty($dm->mutation_out_id) ? '#'.$dm->mutation_out_id : '-' }}
                                                                        </td>
                                                                        <td class="text-center align-middle">
                                                                            @if(!empty($dm->photo_path))
                                                                                <a href="{{ asset('storage/'.$dm->photo_path) }}" target="_blank" class="pd-link">
                                                                                    <img src="{{ asset('storage/'.$dm->photo_path) }}" class="pd-thumb" alt="damaged">
                                                                                </a>
                                                                            @else
                                                                                <span class="pd-muted">-</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            @endif
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                            @endif

                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No items found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($isPending)
                    <div class="pd-sub mt-2">
                        This delivery is still <strong>Pending</strong>. Please confirm received items to update stock & fulfilled quantities.
                    </div>
                @endif
            </div>

            {{-- NOTE --}}
            <div class="mt-3">
                <div class="pd-box">
                    <div class="pd-label">Note (Create / Edit Delivery)</div>
                    <div class="pd-value" style="font-weight:600; white-space:pre-line;">
                        {{ $purchaseDelivery->note ?: '-' }}
                    </div>

                    @if(!empty($purchaseDelivery->note_updated_at))
                        <div class="pd-sub mt-2">
                            Last updated:
                            <strong>{{ \Carbon\Carbon::parse($purchaseDelivery->note_updated_at)->format('d M Y, H:i') }}</strong>
                            • Role: <strong>{{ $purchaseDelivery->note_updated_role ?? '-' }}</strong>
                        </div>
                    @endif
                </div>
            </div>

            {{-- CONFIRM NOTE --}}
            <div class="mt-3">
                <div class="pd-box">
                    <div class="pd-label">Confirmation Note (General)</div>
                    <div class="pd-value" style="font-weight:600; white-space:pre-line;">
                        {{ $purchaseDelivery->confirm_note ?: '-' }}
                    </div>

                    @if(!empty($purchaseDelivery->confirm_note_updated_at))
                        <div class="pd-sub mt-2">
                            Confirmed note updated:
                            <strong>{{ \Carbon\Carbon::parse($purchaseDelivery->confirm_note_updated_at)->format('d M Y, H:i') }}</strong>
                            • Role: <strong>{{ $purchaseDelivery->confirm_note_updated_role ?? '-' }}</strong>
                        </div>
                    @endif
                </div>
            </div>


            {{-- ACTIONS --}}
            <div class="pd-footer">
                <form action="{{ route('purchase-deliveries.destroy', $purchaseDelivery->id) }}"
                      method="POST"
                      class="d-inline-block"
                      onsubmit="return confirm('Are you sure? It will delete the data permanently!');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>

                <div class="pd-actions d-flex gap-2">
                    @if(!$hasInvoice)
                        <a href="{{ route('purchase-deliveries.edit', $purchaseDelivery->id) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    @endif


                    @if($isPending)
                        <a href="{{ route('purchase-deliveries.confirm', $purchaseDelivery->id) }}" class="btn btn-primary btn-sm">
                            Confirm Delivery
                        </a>
                    @else
                       @php
                            $hasInvoice = \Modules\Purchase\Entities\Purchase::whereNull('deleted_at')
                                ->where(function($q) use ($purchaseDelivery){
                                    $q->where('purchase_delivery_id', (int) $purchaseDelivery->id);

                                    if (!empty($purchaseDelivery->purchase_order_id)) {
                                        $q->orWhere('purchase_order_id', (int) $purchaseDelivery->purchase_order_id);
                                    }
                                })
                                ->exists();
                        @endphp


                        @if(!$hasInvoice)
                            <a href="{{ route('purchases.createFromDelivery', ['purchase_delivery' => $purchaseDelivery]) }}"
                            class="btn btn-primary btn-sm">
                                Create Purchase (Invoice)
                            </a>
                        @else
                            <span class="pd-muted">Invoice already created</span>
                        @endif
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>
@endsection

@push('page_scripts')
<script>
document.addEventListener('click', function(e){
    const openBtn = e.target.closest('[data-toggle-perunit]');
    if (openBtn){
        const id = openBtn.getAttribute('data-toggle-perunit');
        const row = document.getElementById(id);
        if (row){
            row.style.display = (row.style.display === 'none' || row.style.display === '') ? '' : 'none';
        }
        return;
    }

    const closeBtn = e.target.closest('[data-close-perunit]');
    if (closeBtn){
        const id = closeBtn.getAttribute('data-close-perunit');
        const row = document.getElementById(id);
        if (row) row.style.display = 'none';
        return;
    }
});
</script>
@endpush

@extends('layouts.app')

@section('title', 'Quality Report')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfers</a></li>
        <li class="breadcrumb-item active">Quality Report</li>
    </ol>
@endsection

@push('page_css')
<style>
    .qr-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .qr-title{margin:0;font-weight:800;font-size:18px;letter-spacing:.2px}
    .qr-sub{margin:2px 0 0;color:#6c757d;font-size:12px}
    .qr-card{border:1px solid rgba(0,0,0,.06);border-radius:14px;box-shadow:0 8px 22px rgba(0,0,0,.05)}
    .qr-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.03);white-space:nowrap}
    .qr-kpi{display:flex;align-items:center;justify-content:space-between;gap:10px}
    .qr-kpi .num{font-size:22px;font-weight:900;line-height:1}
    .qr-kpi .lbl{font-size:12px;color:#6c757d}
    .qr-table thead th{white-space:nowrap}
    .qr-muted{color:#6c757d}
    .badge-soft{border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.03);color:#343a40}
    .badge-soft-warning{background:rgba(255,193,7,.15);border-color:rgba(255,193,7,.35);color:#7a5a00}
    .badge-soft-danger{background:rgba(220,53,69,.10);border-color:rgba(220,53,69,.25);color:#a31526}
    .badge-soft-primary{background:rgba(13,110,253,.10);border-color:rgba(13,110,253,.25);color:#0d6efd}
    .badge-soft-info{background:rgba(13,202,240,.12);border-color:rgba(13,202,240,.35);color:#087990}
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- FILTER CARD --}}
    <div class="card qr-card mb-3">
        <div class="card-body">
            <div class="qr-head mb-3">
                <div>
                    <h4 class="qr-title">Quality Report</h4>
                    <div class="qr-sub">Defect & Issue (Damaged/Missing). Only <b>pending</b> issues are shown. Max 500 rows per section.</div>
                </div>
                <div class="qr-pill">
                    <i class="bi bi-filter"></i> Filters
                </div>
            </div>

            <form method="GET" action="{{ route('transfers.quality-report.index') }}" class="row g-3 align-items-end">

                @if($active === 'all')
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-control">
                            <option value="all">All Branches</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ (int) request('branch_id') === (int) $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-control">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (int) request('warehouse_id') === (int) $wh->id ? 'selected' : '' }}>
                                {{ $wh->warehouse_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="all" {{ request('type', 'all') === 'all' ? 'selected' : '' }}>All</option>
                        <option value="defect" {{ request('type') === 'defect' ? 'selected' : '' }}>Defect</option>
                        <option value="damaged" {{ request('type') === 'damaged' ? 'selected' : '' }}>Issue (Damaged/Missing)</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Search</label>
                    <input type="text"
                           name="q"
                           class="form-control"
                           value="{{ request('q') }}"
                           placeholder="Product / defect type / reason / reference (TRF / PO / PUR / PD / ADJ)..." />
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-search"></i> Apply
                    </button>
                    <a class="btn btn-outline-secondary w-100" href="{{ route('transfers.quality-report.index') }}">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI --}}
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card qr-card">
                <div class="card-body qr-kpi">
                    <div>
                        <div class="lbl">Total Defect Qty</div>
                        <div class="num">{{ number_format((int) $totalDefectQty) }}</div>
                    </div>
                    <span class="qr-pill badge-soft-warning">
                        <i class="bi bi-exclamation-triangle"></i> DEFECT
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card qr-card">
                <div class="card-body qr-kpi">
                    <div>
                        <div class="lbl">Total Pending Issue Qty</div>
                        <div class="num">{{ number_format((int) $totalDamagedQty) }}</div>
                    </div>
                    <span class="qr-pill badge-soft-danger">
                        <i class="bi bi-bug"></i> ISSUE
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- DEFECT --}}
    @if(request('type', 'all') === 'all' || request('type') === 'defect')
        <div class="card qr-card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold">Defect List</div>
                <span class="qr-pill badge-soft-warning"><i class="bi bi-list-check"></i> up to 500</span>
            </div>

            <div class="card-body p-0">
                @if($defects->isEmpty())
                    <div class="p-4 qr-muted">Tidak ada data defect.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0 qr-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th style="width:170px;">Date</th>
                                    <th>Branch</th>
                                    <th>Warehouse</th>
                                    <th>Product</th>
                                    <th class="text-center" style="width:90px;">Qty</th>
                                    <th style="width:180px;">Defect Type</th>
                                    <th>Description</th>
                                    <th class="text-center" style="width:110px;">Photo</th>
                                    <th class="text-center" style="width:220px;">Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($defects as $i => $d)
                                    @php
                                        $refType  = $d->reference_type ?? null;
                                        $refId    = !empty($d->reference_id) ? (int) $d->reference_id : null;
                                        $refLabel = $d->reference_label ?? null;

                                        if (empty($refLabel) && !empty($refType)) $refLabel = 'REF';

                                        $refUrl = null;
                                        if ($refId && $refType === $transferClass) $refUrl = route('transfers.show', $refId);
                                        elseif ($refId && $refType === $purchaseOrderClass) $refUrl = route('purchase-orders.show', $refId);
                                        elseif ($refId && $refType === $purchaseClass) $refUrl = route('purchases.show', $refId);
                                        elseif ($refId && $refType === $purchaseDeliveryClass) $refUrl = route('purchase-deliveries.show', $refId);
                                        elseif ($refId && $refType === $adjustmentClass) $refUrl = route('adjustments.show', $refId);

                                        $badge = 'badge-soft';
                                        if ($refType === $transferClass) $badge = 'badge-soft-primary';
                                        elseif ($refType === $purchaseOrderClass) $badge = 'badge-soft-info';
                                        elseif ($refType === $purchaseClass) $badge = 'badge-soft-primary';
                                        elseif ($refType === $purchaseDeliveryClass) $badge = 'badge-soft';
                                        elseif ($refType === $adjustmentClass) $badge = 'badge-soft';
                                    @endphp

                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($d->created_at)->format('d M Y H:i') }}</td>
                                        <td>{{ $d->branch_name ?? '-' }}</td>
                                        <td>{{ $d->warehouse_name ?? '-' }}</td>
                                        <td>{{ $d->product_name ?? ('Product ID: ' . (int)$d->product_id) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-soft-warning fw-semibold">{{ number_format((int) $d->quantity) }}</span>
                                        </td>
                                        <td>{{ $d->defect_type ?? '-' }}</td>
                                        <td>{{ $d->description ?? '-' }}</td>
                                        <td class="text-center">
                                            @if(!empty($d->photo_path))
                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($d->photo_path) }}" target="_blank" class="btn btn-sm btn-light">
                                                    View
                                                </a>
                                            @else
                                                <span class="qr-muted">-</span>
                                            @endif
                                        </td>

                                        <td class="text-center">
                                            @if($refLabel)
                                                @if($refUrl)
                                                    <a href="{{ $refUrl }}" target="_blank" class="badge {{ $badge }} fw-semibold text-decoration-none">
                                                        {{ $refLabel }}
                                                    </a>
                                                @else
                                                    <span class="badge badge-soft fw-semibold">{{ $refLabel }}</span>
                                                @endif
                                            @else
                                                <span class="qr-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ISSUE (DAMAGED/MISSING) --}}
    @if(request('type', 'all') === 'all' || request('type') === 'damaged')
        <div class="card qr-card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold">Issue List <span class="text-muted fw-normal">(Pending only)</span></div>
                <span class="qr-pill badge-soft-danger"><i class="bi bi-shield-exclamation"></i> resolve from here</span>
            </div>

            <div class="card-body p-0">
                @if($damaged->isEmpty())
                    <div class="p-4 qr-muted">Tidak ada issue pending.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0 qr-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th style="width:170px;">Date</th>
                                    <th>Branch</th>
                                    <th>Warehouse</th>
                                    <th>Product</th>
                                    <th class="text-center" style="width:90px;">Qty</th>
                                    <th style="width:110px;" class="text-center">Type</th>
                                    <th>Reason</th>
                                    <th class="text-center" style="width:110px;">Photo</th>
                                    <th class="text-center" style="width:220px;">Reference</th>
                                    <th class="text-center" style="width:120px;">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($damaged as $i => $dm)
                                    @php
                                        $refType  = $dm->reference_type ?? null;
                                        $refId    = !empty($dm->reference_id) ? (int) $dm->reference_id : null;
                                        $refLabel = $dm->reference_label ?? null;
                                        if (empty($refLabel) && !empty($refType)) $refLabel = 'REF';

                                        $refUrl = null;
                                        if ($refId && $refType === $transferClass) $refUrl = route('transfers.show', $refId);
                                        elseif ($refId && $refType === $purchaseOrderClass) $refUrl = route('purchase-orders.show', $refId);
                                        elseif ($refId && $refType === $purchaseClass) $refUrl = route('purchases.show', $refId);
                                        elseif ($refId && $refType === $purchaseDeliveryClass) $refUrl = route('purchase-deliveries.show', $refId);
                                        elseif ($refId && $refType === $adjustmentClass) $refUrl = route('adjustments.show', $refId);

                                        $damageType = $dm->damage_type ?? 'damaged';
                                        $typeBadge = $damageType === 'missing' ? 'badge-soft-warning' : 'badge-soft-danger';

                                        // current values for modal
                                        $currentCause = $dm->cause ?? 'transfer';
                                        $currentResp  = $dm->responsible_user_id ?? null;
                                        $currentNote  = $dm->resolution_note ?? null;
                                    @endphp

                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($dm->created_at)->format('d M Y H:i') }}</td>
                                        <td>{{ $dm->branch_name ?? '-' }}</td>
                                        <td>{{ $dm->warehouse_name ?? '-' }}</td>
                                        <td>{{ $dm->product_name ?? ('Product ID: ' . (int)$dm->product_id) }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $typeBadge }} fw-semibold">{{ number_format((int) $dm->quantity) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $typeBadge }} text-uppercase fw-semibold">
                                                {{ $damageType }}
                                            </span>
                                        </td>
                                        <td>{{ $dm->reason ?? '-' }}</td>
                                        <td class="text-center">
                                            @if(!empty($dm->photo_path))
                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($dm->photo_path) }}" target="_blank" class="btn btn-sm btn-light">
                                                    View
                                                </a>
                                            @else
                                                <span class="qr-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($refLabel)
                                                @if($refUrl)
                                                    <a href="{{ $refUrl }}" target="_blank" class="badge badge-soft-primary fw-semibold text-decoration-none">
                                                        {{ $refLabel }}
                                                    </a>
                                                @else
                                                    <span class="badge badge-soft fw-semibold">{{ $refLabel }}</span>
                                                @endif
                                            @else
                                                <span class="qr-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-toggle="modal"
                                                data-target="#resolveIssueModal"
                                                data-issue-id="{{ (int) $dm->id }}"
                                                data-cause="{{ $currentCause }}"
                                                data-responsible="{{ $currentResp }}"
                                                data-status="pending"
                                                data-note="{{ e((string) $currentNote) }}"
                                            >
                                                <i class="bi bi-check2-circle"></i> Resolve
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>

{{-- RESOLVE MODAL --}}
<div class="modal fade" id="resolveIssueModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="resolveIssueForm" method="POST" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title fw-bold">Resolve Issue</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Cause</label>
            <select name="cause" id="ri_cause" class="form-control">
              <option value="">-</option>
              <option value="transfer">transfer</option>
              <option value="employee">employee</option>
              <option value="supplier">supplier</option>
              <option value="unknown">unknown</option>
            </select>
          </div>

          <div class="col-md-4" id="ri_responsible_wrap" style="display:none;">
            <label class="form-label">Responsible User</label>
            <select name="responsible_user_id" id="ri_responsible" class="form-control">
              <option value="">Select user</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}">
                  {{ $u->name ?? ('User#'.$u->id) }}
                </option>
              @endforeach
            </select>
            <div class="form-text">Required if cause = employee</div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Resolution Status</label>
            <select name="resolution_status" id="ri_status" class="form-control" required>
              <option value="pending">pending</option>
              <option value="resolved">resolved</option>
              <option value="compensated">compensated</option>
              <option value="waived">waived</option>
            </select>
            <div class="form-text">Choose final outcome</div>
          </div>

          <div class="col-12">
            <label class="form-label">Resolution Note</label>
            <textarea name="resolution_note" id="ri_note" rows="3" class="form-control" placeholder="Optional note (max 1000)"></textarea>
          </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
          <div class="fw-semibold mb-1">Info</div>
          <div class="small">
            Setelah status diubah dari <b>pending</b>, issue ini akan otomatis hilang dari list dan tidak dihitung lagi di stock.
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save2"></i> Save
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    function toggleResponsible(cause) {
        var isEmployee = (cause === 'employee');
        $('#ri_responsible_wrap').toggle(isEmployee);
        if (!isEmployee) $('#ri_responsible').val('');
    }

    // Bootstrap 4 modal event via jQuery
    $('#resolveIssueModal').on('show.bs.modal', function (e) {
        var btn = $(e.relatedTarget);
        if (!btn.length) return;

        var issueId = btn.data('issue-id');
        var cause = btn.data('cause') || '';
        var responsible = btn.data('responsible') || '';
        var status = btn.data('status') || 'pending';
        var note = btn.data('note') || '';

        // set form action to PUT route
        $('#resolveIssueForm').attr('action', "{{ url('/transfers/quality-report/issues') }}/" + issueId + "/resolve");

        // fill values
        $('#ri_cause').val(cause);
        $('#ri_status').val(status);
        $('#ri_note').val(note);

        if (responsible) $('#ri_responsible').val(responsible);
        else $('#ri_responsible').val('');

        toggleResponsible(cause);
    });

    // change handler
    $('#ri_cause').on('change', function () {
        toggleResponsible($(this).val());
    });
});
</script>
@endpush

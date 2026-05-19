@extends('layouts.app')

@section('title', 'Adjustment Details')

@push('page_css')
    @livewireStyles
    <style>
        .qc-badge{
            display:inline-flex;align-items:center;gap:6px;
            padding:6px 10px;border-radius:999px;font-size:12px;
            background:rgba(13,110,253,.08);color:#0d6efd;border:1px solid rgba(13,110,253,.18);
            white-space:nowrap;
        }
        .thumb{
            width:70px;height:70px;object-fit:cover;border-radius:10px;border:1px solid rgba(0,0,0,.08);
            background:#fff;
        }
        .muted{ color:#6c757d; font-size:12px; }

        .cond-badge{
            display:inline-flex;align-items:center;gap:6px;
            padding:6px 10px;border-radius:999px;font-size:12px;
            border:1px solid rgba(0,0,0,.08);
            white-space:nowrap;
        }
        .cond-good{
            background: rgba(25,135,84,.10);
            border-color: rgba(25,135,84,.25);
            color: #198754;
        }
        .cond-defect{
            background: rgba(255,193,7,.12);
            border-color: rgba(255,193,7,.35);
            color: #b88400;
        }
        .cond-damaged{
            background: rgba(220,53,69,.10);
            border-color: rgba(220,53,69,.25);
            color: #dc3545;
        }
    </style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">Adjustments</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">

    @php
        use Carbon\Carbon;
        $creatorName = optional($adjustment->creator)->name ?? optional($adjustment->creator)->username ?? '-';
        $createdAt   = $adjustment->created_at ? Carbon::parse($adjustment->created_at)->format('d M Y H:i') : '-';

        // detect "Quality Reclass"
        $isQuality = false;
        $noteText = (string) ($adjustment->note ?? '');
        if (stripos($noteText, 'Quality Reclass') !== false) {
            $isQuality = true;
        } else {
            foreach ($adjustment->adjustedProducts as $ap) {
                $apNote = (string) ($ap->note ?? '');
                if (stripos($apNote, 'Quality Reclass') !== false || stripos($apNote, 'NET-ZERO') !== false) {
                    $isQuality = true;
                    break;
                }
            }
        }

        $defectItems = $defectItems ?? collect();
        $damagedItems = $damagedItems ?? collect();
        $rackLabelMap = $rackLabelMap ?? [];

        $qualityType = null;
        if ($defectItems->count() > 0) $qualityType = 'defect';
        if ($damagedItems->count() > 0) $qualityType = 'damaged';
        if ($defectItems->count() > 0 && $damagedItems->count() > 0) $qualityType = 'mixed';

        $parseCond = function (?string $note): string {
            $note = (string) $note;
            if ($note === '') return 'GOOD';
            if (preg_match('/\bCOND=([A-Z_]+)\b/i', $note, $m)) {
                $v = strtoupper((string) ($m[1] ?? 'GOOD'));
                if (in_array($v, ['GOOD','DEFECT','DAMAGED'], true)) return $v;
            }
            return 'GOOD';
        };

        $condBadgeClass = function (string $cond): string {
            return match ($cond) {
                'DEFECT' => 'cond-badge cond-defect',
                'DAMAGED' => 'cond-badge cond-damaged',
                default => 'cond-badge cond-good',
            };
        };

        $condIcon = function (string $cond): string {
            return match ($cond) {
                'DEFECT' => 'bi bi-exclamation-triangle',
                'DAMAGED' => 'bi bi-x-octagon',
                default => 'bi bi-check-circle',
            };
        };

        $cleanNote = function (?string $note): string {
            $note = trim((string) $note);
            if ($note === '') return '';

            $parts = array_map('trim', explode('|', $note));
            $parts = array_values(array_filter($parts, function ($part) {
                return $part !== '' && !preg_match('/^COND=/i', $part);
            }));

            return trim(implode(' | ', $parts));
        };

        $conditionFlow = function (?string $note) use ($parseCond): array {
            $note = (string) $note;

            if (preg_match('/\bQRC\s+([A-Z_]+)->([A-Z_]+)\b/i', $note, $m)) {
                return [strtoupper((string) $m[1]), strtoupper((string) $m[2])];
            }

            if (preg_match('/Quality Reclass\s+([A-Z_]+)\s*->\s*([A-Z_]+)/i', $note, $m)) {
                return [strtoupper((string) $m[1]), strtoupper((string) $m[2])];
            }

            $cond = $parseCond($note);
            return [$cond, $cond];
        };

        $unitDirection = function ($unit) use ($adjustment): string {
            if ((int) ($unit->reference_id ?? 0) === (int) $adjustment->id) {
                return 'Added';
            }

            if ((int) ($unit->moved_out_reference_id ?? 0) === (int) $adjustment->id) {
                return 'Reduced / Reclassed Out';
            }

            return '-';
        };

        $rackText = function ($rackId) use ($rackLabelMap): string {
            $rackId = (int) $rackId;
            return $rackLabelMap[$rackId] ?? ($rackId > 0 ? 'Rack#' . $rackId : '-');
        };
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('utils.alerts')

                    @php
                        $status = strtolower((string) ($adjustment->status ?? 'approved'));
                        $statusMap = [
                            'pending' => 'warning text-dark',
                            'approved' => 'success',
                            'rejected' => 'danger',
                        ];
                        $statusClass = $statusMap[$status] ?? 'secondary';
                        $isSuperAdmin = auth()->check() && auth()->user()->hasRole('Super Admin');
                    @endphp

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="badge badge-{{ $statusClass }} px-3 py-2">{{ strtoupper($status ?: 'approved') }}</span>
                            @if(!empty($adjustment->request_type))
                                <span class="badge badge-light border px-3 py-2 ml-1">{{ str_replace('_', ' ', strtoupper($adjustment->request_type)) }}</span>
                            @endif
                        </div>

                        @if($isSuperAdmin && $adjustment->isPending())
                            <div class="d-flex" style="gap:8px;">
                                <form action="{{ route('adjustments.approve', $adjustment) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="approval_note" value="">
                                    <button type="submit" class="btn btn-success" data-confirm-submit-button="true" data-confirm-title="Confirm Approval" data-confirm-message="Approve and execute this adjustment request?" data-confirm-button="Approve" data-confirm-variant="success">
                                        <i class="bi bi-check2"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectAdjustmentModal">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </div>
                        @endif
                    </div>

                    @if($adjustment->isRejected() && !empty($adjustment->rejection_reason))
                        <div class="alert alert-danger">
                            <strong>Rejection Reason:</strong><br>
                            {{ $adjustment->rejection_reason }}
                        </div>
                    @endif

                    @if(!empty($adjustment->approval_note))
                        <div class="alert alert-success">
                            <strong>Approval Note:</strong><br>
                            {{ $adjustment->approval_note }}
                        </div>
                    @endif

                    @if(!$adjustment->isApproved())
                        @php
                            $pendingLines = $adjustment->pendingDisplayLines();
                        @endphp

                        <div class="alert alert-light border">
                            <strong>Request Details</strong>
                            <div class="text-muted small">These lines are pending/rejected request data and have not been posted to stock.</div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width:60px">#</th>
                                        <th>Product</th>
                                        <th>Warehouse</th>
                                        <th>Rack / Allocation</th>
                                        <th class="text-center">Flow</th>
                                        <th class="text-center">Qty</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingLines as $index => $line)
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>
                                                <strong>{{ $line['product_name'] ?? '-' }}</strong>
                                                <div class="muted">Code: {{ $line['product_code'] ?? '-' }}</div>
                                            </td>
                                            <td>
                                                {{ $line['warehouse_name'] ?? '-' }}
                                            </td>
                                            <td>
                                                @foreach((array) ($line['rack_display'] ?? ['-']) as $rackLine)
                                                    <div>{{ $rackLine }}</div>
                                                @endforeach
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-light border">{{ $line['flow'] ?? '-' }}</span>
                                            </td>
                                            <td class="text-center">{{ (int) ($line['qty'] ?? 0) }}</td>
                                            <td>
                                                @forelse((array) ($line['details'] ?? []) as $detail)
                                                    <div class="mb-1">
                                                        @if(!empty($detail['label']))
                                                            <span class="badge badge-secondary">{{ $detail['label'] }}</span>
                                                        @endif
                                                        <span>{{ $detail['text'] ?? '-' }}</span>
                                                        @if(!empty($detail['photo_path']))
                                                            <a href="{{ asset('storage/'.$detail['photo_path']) }}" target="_blank" rel="noopener" class="badge badge-info ml-1">
                                                                View Photo
                                                            </a>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <span class="muted">-</span>
                                                @endforelse
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center muted">No request items found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(!empty($adjustment->note))
                        <div class="alert alert-info mb-3 d-flex align-items-center justify-content-between">
                            <div>
                                <strong>Adjustment Note:</strong><br>
                                {{ $adjustment->note }}
                            </div>

                            @if($isQuality)
                                <span class="qc-badge">
                                    <i class="bi bi-shield-check"></i>
                                    Quality Reclass
                                    @if($qualityType && $qualityType !== 'mixed')
                                        ({{ $qualityType }})
                                    @endif
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th colspan="2">Date</th>
                                <th colspan="2">Reference</th>
                            </tr>
                            <tr>
                                <td colspan="2">{{ $adjustment->date }}</td>
                                <td colspan="2">{{ $adjustment->reference }}</td>
                            </tr>

                            <tr>
                                <th colspan="2">Warehouse</th>
                                <th colspan="2">Branch</th>
                            </tr>
                            <tr>
                                <td colspan="2">{{ optional($adjustment->warehouse)->warehouse_name ?? '-' }}</td>
                                <td colspan="2">{{ optional($adjustment->branch)->name ?? '-' }}</td>
                            </tr>

                            <tr>
                                <th colspan="2">Created By</th>
                                <th colspan="2">Created At</th>
                            </tr>
                            <tr>
                                <td colspan="2">{{ $creatorName }}</td>
                                <td colspan="2">{{ $createdAt }}</td>
                            </tr>

                            @if($adjustment->isApproved())
                                <tr>
                                    <th>Product</th>
                                    <th>Rack</th>
                                    <th class="text-center" style="width:210px">Condition / Flow</th>
                                    <th class="text-center" style="width:170px">Type / Qty</th>
                                </tr>
                            @endif

                            @if($adjustment->isApproved())
                            @foreach($adjustment->adjustedProducts as $adjustedProduct)
                                @php
                                    [$sourceCond, $targetCond] = $conditionFlow($adjustedProduct->note ?? '');
                                    $cond = $targetCond ?: $sourceCond;
                                    $badgeCls = $condBadgeClass($cond);
                                    $iconCls = $condIcon($cond);
                                    $itemNote = $cleanNote($adjustedProduct->note ?? '');
                                    $rowDefectItems = $defectItems
                                        ->where('product_id', (int) $adjustedProduct->product_id)
                                        ->where('rack_id', (int) $adjustedProduct->rack_id);
                                    $rowDamagedItems = $damagedItems
                                        ->where('product_id', (int) $adjustedProduct->product_id)
                                        ->where('rack_id', (int) $adjustedProduct->rack_id);

                                    $typeText = $isQuality
                                        ? 'Quality Reclass'
                                        : (($adjustedProduct->type === 'add') ? 'Add' : 'Sub');

                                    $typePrefix = $isQuality ? '' : (($adjustedProduct->type === 'add') ? '(+)' : '(-)');
                                @endphp

                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $adjustedProduct->product->product_name ?? '-' }}</strong>
                                        </div>
                                        <div class="muted">
                                            Code: {{ $adjustedProduct->product->product_code ?? '-' }}
                                        </div>
                                        @if($itemNote !== '')
                                            <div class="mt-2">
                                                <span class="muted">Description / Note:</span><br>
                                                {{ $itemNote }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        @if($adjustedProduct->rack)
                                            {{ $adjustedProduct->rack->code ?? '-' }} - {{ $adjustedProduct->rack->name ?? '-' }}
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @if($isQuality)
                                            <span class="qc-badge">
                                                <i class="bi bi-shield-check"></i>
                                                {{ $sourceCond }} → {{ $targetCond }}
                                            </span>
                                        @else
                                            <span class="{{ $badgeCls }}">
                                                <i class="{{ $iconCls }}"></i>
                                                {{ $cond }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <div>
                                            <strong>{{ $typePrefix }} {{ $typeText }}</strong>
                                        </div>
                                        <div class="muted">
                                            Qty: {{ (int) $adjustedProduct->quantity }}
                                        </div>
                                    </td>
                                </tr>

                                @if(in_array('DEFECT', [$sourceCond, $targetCond], true) && $rowDefectItems->count() > 0)
                                    <tr>
                                        <td colspan="4">
                                            <strong>Defect Information:</strong>
                                            <div class="mt-2">
                                                @foreach($rowDefectItems as $unit)
                                                    <div class="mb-2">
                                                        <span class="badge badge-warning">Unit #{{ $unit->id }}</span>
                                                        <span class="muted ml-1">Defect Type:</span>
                                                        {{ \App\Support\DefectTypeSupport::text($unit->defect_types ?? [], '-') }}
                                                        @if(!empty($unit->description))
                                                            <span class="muted ml-2">Description:</span> {{ $unit->description }}
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @if(in_array('DAMAGED', [$sourceCond, $targetCond], true) && $rowDamagedItems->count() > 0)
                                    <tr>
                                        <td colspan="4">
                                            <strong>Damaged Information:</strong>
                                            <div class="mt-2">
                                                @foreach($rowDamagedItems as $unit)
                                                    <div class="mb-2">
                                                        <span class="badge badge-danger">Unit #{{ $unit->id }}</span>
                                                        <span class="muted ml-1">Damage Type:</span>
                                                        {{ $unit->damage_type ?? '-' }}
                                                        @if(!empty($unit->reason))
                                                            <span class="muted ml-2">Reason:</span> {{ $unit->reason }}
                                                        @endif
                                                        @if(!empty($unit->resolution_note))
                                                            <span class="muted ml-2">Description:</span> {{ $unit->resolution_note }}
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            @endif
                        </table>
                    </div>

                    @if($defectItems->count() > 0 || $damagedItems->count() > 0)
                        <div class="mt-4">

                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Defect / Damaged Unit Details</h5>
                                <div class="muted">
                                    Showing per-unit records created or consumed by this adjustment.
                                </div>
                            </div>

                            @if($defectItems->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width:60px" class="text-center">#</th>
                                                <th>Product</th>
                                                <th>Rack</th>
                                                <th style="width:150px">Direction</th>
                                                <th>Defect Type</th>
                                                <th>Description</th>
                                                <th style="width:120px" class="text-center">Photo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($defectItems as $i => $it)
                                                @php
                                                    $product = $it->product;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i+1 }}</td>
                                                    <td>
                                                        <strong>{{ $product->product_name ?? '-' }}</strong>
                                                        <div class="muted">Code: {{ $product->product_code ?? '-' }}</div>
                                                    </td>
                                                    <td>{{ $rackText($it->rack_id ?? 0) }}</td>
                                                    <td>{{ $unitDirection($it) }}</td>
                                                    <td>{{ \App\Support\DefectTypeSupport::text($it->defect_types ?? [], '-') }}</td>
                                                    <td>{{ $it->description ?? '-' }}</td>
                                                    <td class="text-center">
                                                        @if(!empty($it->photo_path))
                                                            @php $url = asset('storage/'.$it->photo_path); @endphp
                                                            <a href="{{ $url }}" target="_blank" rel="noopener">
                                                                <img src="{{ $url }}" class="thumb" alt="photo">
                                                            </a>
                                                        @else
                                                            <span class="muted">No photo</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center muted">No defect items found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if($damagedItems->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width:60px" class="text-center">#</th>
                                                <th>Product</th>
                                                <th>Rack</th>
                                                <th style="width:150px">Direction</th>
                                                <th>Damage Type</th>
                                                <th>Reason</th>
                                                <th>Description</th>
                                                <th style="width:120px" class="text-center">Photo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($damagedItems as $i => $it)
                                                @php
                                                    $product = $it->product;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i+1 }}</td>
                                                    <td>
                                                        <strong>{{ $product->product_name ?? '-' }}</strong>
                                                        <div class="muted">Code: {{ $product->product_code ?? '-' }}</div>
                                                    </td>
                                                    <td>{{ $rackText($it->rack_id ?? 0) }}</td>
                                                    <td>{{ $unitDirection($it) }}</td>
                                                    <td>{{ $it->damage_type ?? '-' }}</td>
                                                    <td>{{ $it->reason ?? '-' }}</td>
                                                    <td>{{ $it->resolution_note ?? '-' }}</td>
                                                    <td class="text-center">
                                                        @if(!empty($it->photo_path))
                                                            @php $url = asset('storage/'.$it->photo_path); @endphp
                                                            <a href="{{ $url }}" target="_blank" rel="noopener">
                                                                <img src="{{ $url }}" class="thumb" alt="photo">
                                                            </a>
                                                        @else
                                                            <span class="muted">No photo</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center muted">No damaged items found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

</div>

@include('includes.edit-activity-log', ['model' => $adjustment])

@if(auth()->check() && auth()->user()->hasRole('Super Admin') && $adjustment->isPending())
    <div class="modal fade" id="rejectAdjustmentModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" action="{{ route('adjustments.reject', $adjustment) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Adjustment Request</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

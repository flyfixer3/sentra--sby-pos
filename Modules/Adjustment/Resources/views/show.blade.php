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

        /* ✅ Condition badges */
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
        use Modules\Product\Entities\ProductDefectItem;
        use Modules\Product\Entities\ProductDamagedItem;

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

        // per-unit rows linked to this adjustment (for QC section)
        $defectItems = ProductDefectItem::query()
            ->where('reference_type', \Modules\Adjustment\Entities\Adjustment::class)
            ->where('reference_id', $adjustment->id)
            ->orderBy('id')
            ->get();

        $damagedItems = ProductDamagedItem::query()
            ->where('reference_type', \Modules\Adjustment\Entities\Adjustment::class)
            ->where('reference_id', $adjustment->id)
            ->orderBy('id')
            ->get();

        $qualityType = null;
        if ($defectItems->count() > 0) $qualityType = 'defect';
        if ($damagedItems->count() > 0) $qualityType = 'damaged';
        if ($defectItems->count() > 0 && $damagedItems->count() > 0) $qualityType = 'mixed';

        // helper parsing condition from adjustedProduct.note:
        // note format example: "Item: ... | COND=DEFECT | DEFECT_TYPE=..."
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
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

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

                            {{-- ✅ Item list --}}
                            <tr>
                                <th>Product</th>
                                <th>Rack</th>
                                <th class="text-center" style="width:160px">Condition</th>
                                <th class="text-center" style="width:170px">Type / Qty</th>
                            </tr>

                            @foreach($adjustment->adjustedProducts as $adjustedProduct)
                                @php
                                    $cond = $parseCond($adjustedProduct->note ?? '');
                                    $badgeCls = $condBadgeClass($cond);
                                    $iconCls = $condIcon($cond);

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
                                                Quality
                                                @if($qualityType && $qualityType !== 'mixed')
                                                    ({{ $qualityType }})
                                                @endif
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

                                @if(!empty($adjustedProduct->note))
                                    <tr>
                                        <td colspan="4">
                                            <strong>Item Note:</strong><br>
                                            {{ $adjustedProduct->note }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </table>
                    </div>

                    {{-- QC details only when quality reclass --}}
                    @if($isQuality)
                        <div class="mt-4">

                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Quality Reclass Details</h5>
                                <div class="muted">
                                    Showing per-unit records linked to this adjustment.
                                </div>
                            </div>

                            @if($qualityType === 'defect')
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width:60px" class="text-center">#</th>
                                                <th>Rack</th>
                                                <th>Defect Type</th>
                                                <th>Description</th>
                                                <th style="width:120px" class="text-center">Photo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($defectItems as $i => $it)
                                                <tr>
                                                    <td class="text-center">{{ $i+1 }}</td>
                                                    <td>{{ $it->rack_id ?? '-' }}</td>
                                                    <td>{{ $it->defect_type ?? '-' }}</td>
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
                                                    <td colspan="5" class="text-center muted">No defect items found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            @elseif($qualityType === 'damaged')
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width:60px" class="text-center">#</th>
                                                <th>Rack</th>
                                                <th>Reason</th>
                                                <th>Description</th>
                                                <th style="width:120px" class="text-center">Photo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($damagedItems as $i => $it)
                                                <tr>
                                                    <td class="text-center">{{ $i+1 }}</td>
                                                    <td>{{ $it->rack_id ?? '-' }}</td>
                                                    <td>{{ $it->reason ?? '-' }}</td>
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
                                                    <td colspan="5" class="text-center muted">No damaged items found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            @elseif($qualityType === 'mixed')
                                <div class="alert alert-warning">
                                    QC items contain both <b>defect</b> and <b>damaged</b>. This is unusual; please check data.
                                </div>

                            @else
                                <div class="alert alert-light border">
                                    No per-unit QC records found for this adjustment.
                                </div>
                            @endif

                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

</div>
@endsection

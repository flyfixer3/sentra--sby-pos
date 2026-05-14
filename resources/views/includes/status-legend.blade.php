@php
    $legendId = $id ?? ('statusLegend' . md5(($title ?? 'Status Legend') . json_encode($items ?? [])));
    $legendTitle = $title ?? 'Status Legend';
    $legendItems = $items ?? [];
@endphp

<div class="mb-3">
    <button
        class="btn btn-sm btn-outline-info"
        type="button"
        data-toggle="collapse"
        data-target="#{{ $legendId }}"
        data-coreui-toggle="collapse"
        data-coreui-target="#{{ $legendId }}"
        aria-expanded="false"
        aria-controls="{{ $legendId }}"
    >
        <i class="bi bi-info-circle"></i> Status Legend
    </button>

    <div class="collapse mt-2" id="{{ $legendId }}">
        <div class="border rounded bg-light p-3">
            <div class="font-weight-bold mb-2">{{ $legendTitle }}</div>
            <div class="table-responsive mb-0">
                <table class="table table-sm table-bordered bg-white mb-0">
                    <thead>
                        <tr>
                            <th style="width: 170px;">Status</th>
                            <th>Meaning</th>
                            <th>Trigger / When It Happens</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($legendItems as $item)
                            <tr>
                                <td class="align-middle">
                                    <span class="{{ $item['badge_class'] ?? 'badge badge-secondary' }}">
                                        {{ $item['status'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="align-middle">{{ $item['meaning'] ?? '-' }}</td>
                                <td class="align-middle">{{ $item['trigger'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

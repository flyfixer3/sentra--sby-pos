@if(!empty($data->deleted_at))
    <span class="badge badge-danger">
        Deleted
    </span>
@else
    @php
        $s = strtolower((string) $data->status);
    @endphp

    @if($s === 'completed')
        <span class="badge badge-success">Completed</span>
    @elseif($s === 'ordered')
        <span class="badge badge-info">Ordered</span>
    @elseif($s === 'pending')
        <span class="badge badge-warning">Pending</span>
    @else
        <span class="badge badge-secondary">{{ $data->status ?? '-' }}</span>
    @endif
@endif

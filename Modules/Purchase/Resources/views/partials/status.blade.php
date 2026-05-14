@if(!empty($data->deleted_at))
    <span class="badge badge-danger">
        Deleted
    </span>
@else
    @php
        $status = trim((string) ($data->status ?? ''));
        $s = strtolower($status);
    @endphp

    @if($s === 'completed')
        <span class="badge badge-success">Completed</span>
    @elseif($s === 'pending')
        <span class="badge badge-warning">Pending</span>
    @else
        <span class="badge badge-secondary">{{ $status !== '' ? $status : '-' }}</span>
    @endif
@endif

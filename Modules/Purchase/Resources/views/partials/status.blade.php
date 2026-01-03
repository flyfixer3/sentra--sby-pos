@if(!empty($data->deleted_at))
    <span class="badge bg-secondary">Deleted</span>
@else
    @php $st = strtolower((string)($data->status ?? 'pending')); @endphp

    @if($st === 'completed')
        <span class="badge bg-success">Completed</span>
    @elseif($st === 'shipped')
        <span class="badge bg-primary">Shipped</span>
    @else
        <span class="badge bg-warning text-dark">Pending</span>
    @endif
@endif

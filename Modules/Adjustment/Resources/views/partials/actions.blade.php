@php
    $status = strtolower((string) ($data->status ?? 'approved'));
    $isSuperAdmin = auth()->check() && auth()->user()->hasRole('Super Admin');
@endphp

@if($isSuperAdmin && $status === 'pending')
    <form action="{{ route('adjustments.approve', $data->id) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve and execute this adjustment request?')">
            <i class="bi bi-check2"></i>
        </button>
    </form>
@endif

@can('edit_adjustments')
@if($status === 'approved')
    <a href="{{ route('adjustments.edit', $data->id) }}" class="btn btn-info btn-sm">
        <i class="bi bi-pencil"></i>
    </a>
@endif
@endcan
@can('show_adjustments')
    <a href="{{ route('adjustments.show', $data->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-eye"></i>
    </a>
@endcan
@can('delete_adjustments')
    <button id="delete" class="btn btn-danger btn-sm" onclick="
        event.preventDefault();
        if (confirm('Are you sure? It will delete the data permanently!')) {
        document.getElementById('destroy{{ $data->id }}').submit()
        }
        ">
        <i class="bi bi-trash"></i>
        <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('adjustments.destroy', $data->id) }}" method="POST">
            @csrf
            @method('delete')
        </form>
    </button>
@endcan

@php
    $status = strtolower(trim((string) ($data->status ?? 'pending')));
    $active = session('active_branch');
@endphp

@can('confirm_transfers')
    @if($status === 'shipped' && $active !== 'all' && (int)$data->to_branch_id === (int)$active)
        <a href="{{ route('transfers.confirm', $data->id) }}" class="btn btn-sm btn-success" title="Konfirmasi Transfer">
            <i class="bi bi-box-arrow-in-down"></i> Confirm
        </a>
    @endif
@endcan

@can('cancel_transfers')
    @if(in_array($status, ['shipped','confirmed'], true))
        <button type="button"
                class="btn btn-sm btn-danger js-open-cancel-transfer"
                data-transfer-id="{{ $data->id }}"
                data-transfer-ref="{{ $data->reference ?? ('#'.$data->id) }}"
                title="Cancel Transfer">
            <i class="bi bi-x-circle"></i> Cancel
        </button>
    @endif
@endcan


@can('show_transfers')
    <a href="{{ route('transfers.show', $data->id) }}" class="btn btn-sm btn-primary" title="Lihat Detail Transfer">
        <i class="bi bi-eye"></i> Detail
    </a>
@endcan

@if ($data->printed_at)
    <button type="button"
            class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="modal"
            data-bs-target="#modalGlobalReprint"
            data-id="{{ $data->id }}"
            data-reference="{{ $data->reference }}"
            data-count="{{ $data->printLogs->count() }}"
            title="Sudah dicetak {{ $data->printLogs->count() }}x">
        <i class="bi bi-printer"></i>
    </button>
@endif

@can('delete_transfers')
    <button id="delete" class="btn btn-danger btn-sm" onclick="
        event.preventDefault();
        if (confirm('Are you sure? It will delete the data permanently!')) {
            document.getElementById('destroy{{ $data->id }}').submit()
        }">
        <i class="bi bi-trash"></i>
    </button>

    <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('transfers.destroy', $data->id) }}" method="POST">
        @csrf
        @method('delete')
    </form>
@endcan

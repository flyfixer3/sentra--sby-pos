@can('confirm_transfers')
    <a href="{{ route('transfers.confirm', $data->id) }}" class="btn btn-sm btn-success" title="Konfirmasi Transfer">
        <i class="bi bi-box-arrow-in-down"></i> Confirm
    </a>
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

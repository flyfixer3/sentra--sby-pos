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
                class="btn btn-sm btn-danger"
                data-bs-toggle="modal"
                data-bs-target="#cancelModal-{{ $data->id }}"
                title="Cancel Transfer">
            <i class="bi bi-x-circle"></i> Cancel
        </button>

        <div class="modal fade" id="cancelModal-{{ $data->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('transfers.cancel', $data->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Transfer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <div class="small text-muted">Reference</div>
                            <div class="fw-bold">{{ $data->reference }}</div>
                        </div>

                        <label class="form-label">Reason</label>
                        <textarea name="note" class="form-control" rows="3" required
                                  placeholder="Isi alasan cancel..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Yes, Cancel</button>
                    </div>
                </form>
            </div>
        </div>
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

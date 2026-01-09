@php
    $status = strtolower(trim((string) ($data->status ?? 'pending')));
    $active = session('active_branch');

    $isAll = ($active === 'all' || $active === null || $active === '');
    $isSender = (!$isAll && (int)$data->branch_id === (int)$active);
    $canPrint = $isSender && $status !== 'cancelled';
@endphp

@can('print_transfers')
    @if($canPrint)
        <button type="button"
                class="btn btn-sm btn-dark js-open-print-transfer"
                data-transfer-id="{{ $data->id }}"
                data-transfer-ref="{{ $data->reference ?? ('#'.$data->id) }}"
                title="Print Delivery Note">
            <i class="bi bi-printer"></i>
        </button>
    @endif
@endcan

@can('confirm_transfers')
    @if($status === 'shipped' && $active !== 'all' && (int)$data->to_branch_id === (int)$active)
        <a href="{{ route('transfers.confirm', $data->id) }}" class="btn btn-sm btn-success" title="Konfirmasi Transfer">
            <i class="bi bi-box-arrow-in-down"></i> Confirm
        </a>
    @endif
@endcan

@can('cancel_transfers')
    @if(in_array($status, ['shipped','confirmed','issue'], true))
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

@can('delete_transfers')
    <button type="button"
            class="btn btn-danger btn-sm js-delete-transfer"
            data-id="{{ $data->id }}"
            data-reference="{{ $data->reference ?? ('#'.$data->id) }}"
            title="Delete Transfer">
        <i class="bi bi-trash"></i>
    </button>

    <form id="destroy-transfer-{{ $data->id }}"
          class="d-none"
          action="{{ route('transfers.destroy', $data->id) }}"
          method="POST">
        @csrf
        @method('DELETE')
    </form>
@endcan

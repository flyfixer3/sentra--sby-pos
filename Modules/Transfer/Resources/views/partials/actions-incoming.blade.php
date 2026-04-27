@php
    // Tampilkan konfirmasi hanya jika status belum confirmed
    $allowConfirm = in_array(($row->status ?? 'pending'), ['pending', 'shipped']);
@endphp

<div class="btn-group btn-group-sm" role="group">
    @can('confirm_transfers')
        @if($allowConfirm)
            <a href="{{ route('transfers.confirm', $row->id) }}" class="btn btn-success">
                <i class="bi bi-check2-circle"></i> Confirm
            </a>
        @endif
    @endcan

    <a href="{{ route('transfers.show', $row->id) }}" class="btn btn-info">
        <i class="bi bi-eye"></i> Detail
    </a>
</div>

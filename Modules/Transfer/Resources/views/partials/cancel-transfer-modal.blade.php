@php
    $canCancel = isset($transfer) && in_array(strtolower(trim((string)$transfer->status)), ['confirmed','shipped'], true);
    $modalId = 'cancelTransferModal-'.$transfer->id;
@endphp

@can('cancel_transfers')
    @if($canCancel)
        <button type="button"
                class="btn btn-sm btn-danger"
                data-toggle="modal"
                data-target="#{{ $modalId }}">
            <i class="bi bi-x-circle"></i> Cancel Transfer
        </button>

        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="POST" action="{{ route('transfers.cancel', $transfer->id) }}">
                    @csrf

                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                Cancel Transfer - {{ $transfer->reference ?? ('#'.$transfer->id) }}
                            </h5>

                            {{-- Bootstrap 4 / CoreUI close --}}
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <strong>Warning:</strong>
                                Cancel akan membuat <strong>reversal mutation</strong> (log mutation tidak dihapus).
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cancel Reason / Note</label>
                                <textarea class="form-control"
                                          name="note"
                                          rows="4"
                                          required
                                          placeholder="Contoh: Barang rusak saat pengiriman, return ke gudang asal..."></textarea>
                                <div class="form-text">Wajib diisi agar histori jelas.</div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                Close
                            </button>
                            <button type="submit" class="btn btn-danger">Yes, Cancel Transfer</button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    @endif
@endcan

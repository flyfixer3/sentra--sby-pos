@php
    $status = strtolower((string) ($data->status ?? 'approved'));
    $isSuperAdmin = auth()->check() && auth()->user()->hasRole('Super Admin');
@endphp

<div class="btn-group dropleft">
    <button type="button" class="btn btn-ghost-primary dropdown rounded" data-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-three-dots-vertical mr-1" style="line-height: 1;"></i> Actions
    </button>

    <div class="dropdown-menu">
        @can('edit_adjustments')
            @if($status === 'pending')
                <a href="{{ route('adjustments.edit', $data->id) }}" class="dropdown-item">
                    <i class="bi bi-pencil mr-2 text-primary" style="line-height: 1;"></i> Edit
                </a>
            @endif
        @endcan

        @can('show_adjustments')
            <a href="{{ route('adjustments.show', $data->id) }}" class="dropdown-item">
                <i class="bi bi-eye mr-2 text-info" style="line-height: 1;"></i> View Details
            </a>
        @endcan

        @if($isSuperAdmin && $status === 'pending')
            <div class="dropdown-divider"></div>

            <form action="{{ route('adjustments.approve', $data->id) }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="dropdown-item text-success" data-confirm-submit-button="true" data-confirm-title="Confirm Approval" data-confirm-message="Approve and execute this adjustment request?" data-confirm-button="Approve" data-confirm-variant="success">
                    <i class="bi bi-check2 mr-2 text-success" style="line-height: 1;"></i> Approve
                </button>
            </form>

            <form action="{{ route('adjustments.reject', $data->id) }}" method="POST" class="m-0" id="reject-adjustment-{{ $data->id }}">
                @csrf
                <input type="hidden" name="rejection_reason" id="reject-reason-{{ $data->id }}">
                <button type="submit" class="dropdown-item text-warning" onclick="
                    event.preventDefault();
                    var reason = prompt('Reason for rejecting this adjustment request?');
                    if (reason && reason.trim().length >= 2) {
                        document.getElementById('reject-reason-{{ $data->id }}').value = reason.trim();
                        document.getElementById('reject-adjustment-{{ $data->id }}').submit();
                    }
                ">
                    <i class="bi bi-x-circle mr-2 text-warning" style="line-height: 1;"></i> Reject
                </button>
            </form>
        @endif

        @can('delete_adjustments')
            <div class="dropdown-divider"></div>

            <button id="delete" class="dropdown-item text-danger" type="button" data-confirm-target-form="destroy{{ $data->id }}" data-confirm-title="Confirm Delete" data-confirm-message="Are you sure? It will delete the data permanently!" data-confirm-button="Delete" data-confirm-variant="danger">
                <i class="bi bi-trash mr-2 text-danger" style="line-height: 1;"></i> Delete
            </button>
            <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('adjustments.destroy', $data->id) }}" method="POST">
                @csrf
                @method('delete')
            </form>
        @endcan
    </div>
</div>

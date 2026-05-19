@extends('layouts.app')

@section('title', 'Inbox')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Inbox</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>Inbox</strong>
                    <span class="text-muted small ml-2">Pending adjustment requests</span>
                </div>
                <div class="card-body">
                    @include('utils.alerts')

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Request Type</th>
                                    <th>Branch</th>
                                    <th>Warehouse</th>
                                    <th>Submitted By</th>
                                    <th>Submitted At</th>
                                    <th>Note</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adjustments as $adjustment)
                                    <tr>
                                        <td>{{ $adjustment->reference }}</td>
                                        <td>{{ $adjustment->date }}</td>
                                        <td>{{ str_replace('_', ' ', strtoupper($adjustment->request_type ?? '-')) }}</td>
                                        <td>{{ optional($adjustment->branch)->name ?? '-' }}</td>
                                        <td>{{ optional($adjustment->warehouse)->warehouse_name ?? '-' }}</td>
                                        <td>{{ optional($adjustment->creator)->name ?? '-' }}</td>
                                        <td>{{ $adjustment->submitted_at ? $adjustment->submitted_at->format('d-m-Y H:i') : '-' }}</td>
                                        <td>{{ $adjustment->note ?? '-' }}</td>
                                        <td><span class="badge badge-warning text-dark">PENDING</span></td>
                                        <td class="text-center">
                                            <a href="{{ route('adjustments.show', $adjustment) }}" class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            @if($isSuperAdmin)
                                                <form action="{{ route('adjustments.approve', $adjustment) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm" data-confirm-submit-button="true" data-confirm-title="Confirm Approval" data-confirm-message="Approve and execute this adjustment request?" data-confirm-button="Approve" data-confirm-variant="success">
                                                        <i class="bi bi-check2"></i>
                                                    </button>
                                                </form>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm"
                                                        data-toggle="modal"
                                                        data-target="#rejectModal{{ $adjustment->id }}">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>

                                                <div class="modal fade text-left" id="rejectModal{{ $adjustment->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                                    <div class="modal-dialog" role="document">
                                                        <form class="modal-content" action="{{ route('adjustments.reject', $adjustment) }}" method="POST">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reject {{ $adjustment->reference }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                                                                <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">No pending adjustment requests.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $adjustments->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Transfer Requests')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active">Transfers</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    @include('utils.alerts')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Transfer List</strong>
            <a href="{{ route('transfers.create') }}" class="btn btn-primary btn-sm">+ New Transfer</a>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>From Warehouse</th>
                        <th>To Warehouse</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transfers as $transfer)
                        <tr>
                            <td>{{ $transfer->date }}</td>
                            <td>{{ $transfer->reference }}</td>
                            <td>{{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</td>
                            <td>{{ $transfer->toWarehouse->warehouse_name ?? '-' }}</td>
                            <td>
                                @if ($transfer->status == 'confirmed')
                                    <span class="badge bg-success">Confirmed</span>
                                @elseif ($transfer->status == 'rejected')
                                    <span class="badge bg-danger">Rejected</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if ($transfer->status == 'pending' && $transfer->toWarehouse->branch_id == session('active_branch'))
                                    <form action="{{ route('transfers.confirm', $transfer->id) }}" method="POST" onsubmit="return confirm('Confirm this transfer?')">
                                        @csrf
                                        <button class="btn btn-success btn-sm">Confirm</button>
                                    </form>
                                @else
                                    <em>-</em>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

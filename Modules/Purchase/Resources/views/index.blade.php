@extends('layouts.app')

@section('title', 'Purchases')

@section('content')
<div class="container">
    <!-- ✅ Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-left-warning">
                <div class="card-body">
                    <h6 class="text-warning">Unpaid Invoices</h6>
                    <h4 class="font-weight-bold">Rp. 0,00</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-left-danger">
                <div class="card-body">
                    <h6 class="text-danger">Overdue Invoices</h6>
                    <h4 class="font-weight-bold">Rp. 0,00</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-left-success">
                <div class="card-body">
                    <h6 class="text-success">Payments Sent Last 30 Days</h6>
                    <h4 class="font-weight-bold">Rp. 0,00</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-left-info">
                <div class="card-body">
                    <p class="text-muted small">Free send payment 100x per month</p>
                    <a href="#" class="text-primary">Check Mekari Pay</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ Navigation Tabs -->
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" href="#">Invoice</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Join Invoice</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Delivery</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Order</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Quotation</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Request</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Require Approval <span class="badge badge-danger">0</span></a></li>
    </ul>

    <!-- ✅ Filter & Search Bar -->
    <div class="d-flex align-items-center my-3">
        <select class="form-control w-auto">
            <option>All status</option>
            <option>Paid</option>
            <option>Unpaid</option>
            <option>Overdue</option>
        </select>
        <input type="text" class="form-control mx-2" placeholder="Search transaction">
        <button class="btn btn-outline-secondary">
            <i class="bi bi-filter"></i> Filter
        </button>
    </div>

    <!-- ✅ Transaction List (Empty State) -->
    <div class="card">
        <div class="card-body text-center">
            <img src="{{ asset('assets/no-data.svg') }}" alt="No transactions" width="150">
            <h5 class="mt-3">No transaction yet</h5>
            <p class="text-muted">Your transaction list will appear here.</p>
        </div>
    </div>
</div>
@endsection

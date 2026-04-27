@extends('layouts.app')

@section('title', 'Customer Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                @include('utils.alerts')
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Customer Name</th>
                                    <td>{{ $customer->customer_name }}</td>
                                </tr>
                                <tr>
                                    <th>Customer Email</th>
                                    <td>{{ $customer->customer_email }}</td>
                                </tr>
                                <tr>
                                    <th>Customer Phone</th>
                                    <td>{{ $customer->customer_phone }}</td>
                                </tr>
                                <tr>
                                    <th>City</th>
                                    <td>{{ $customer->city }}</td>
                                </tr>
                                <tr>
                                    <th>Country</th>
                                    <td>{{ $customer->country }}</td>
                                </tr>
                                <tr>
                                    <th>Address</th>
                                    <td>{{ $customer->address }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center">
                        <strong>Vehicles</strong>

                        @can('edit_customers')
                            <button type="button" class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#addVehicleModal">
                                Add Vehicle <i class="bi bi-plus"></i>
                            </button>
                        @endcan
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Vehicle Name</th>
                                        <th>Car Plate</th>
                                        <th>Chassis Number</th>
                                        <th>Note</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        @can('edit_customers')
                                            <th class="text-center">Action</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($vehicles ?? [] as $vehicle)
                                        <tr>
                                            <td>{{ $vehicle->vehicle_name ?? '-' }}</td>
                                            <td><strong>{{ $vehicle->car_plate }}</strong></td>
                                            <td>{{ $vehicle->chassis_number ?? '-' }}</td>
                                            <td>{{ $vehicle->note ?? '-' }}</td>
                                            <td>{{ $vehicle->created_at ? \Carbon\Carbon::parse($vehicle->created_at)->format('d M, Y H:i') : '-' }}</td>
                                            <td>{{ $vehicle->updated_at ? \Carbon\Carbon::parse($vehicle->updated_at)->format('d M, Y H:i') : '-' }}</td>
                                            @can('edit_customers')
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#editVehicleModal{{ $vehicle->id }}">
                                                        Edit
                                                    </button>
                                                    <form action="{{ route('customers.vehicles.destroy', [$customer, $vehicle]) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this vehicle?')">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            @endcan
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->check() && auth()->user()->can('edit_customers') ? 7 : 6 }}" class="text-center text-muted">
                                                No vehicles registered for this customer.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('edit_customers')
        <div class="modal fade" id="addVehicleModal" tabindex="-1" role="dialog" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('customers.vehicles.store', $customer) }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addVehicleModalLabel">Add Vehicle</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @include('people::customers.partials.vehicle-form', ['vehicle' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Vehicle</button>
                    </div>
                </form>
            </div>
        </div>

        @foreach($vehicles ?? [] as $vehicle)
            <div class="modal fade" id="editVehicleModal{{ $vehicle->id }}" tabindex="-1" role="dialog" aria-labelledby="editVehicleModalLabel{{ $vehicle->id }}" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form action="{{ route('customers.vehicles.update', [$customer, $vehicle]) }}" method="POST" class="modal-content">
                        @csrf
                        @method('patch')
                        <div class="modal-header">
                            <h5 class="modal-title" id="editVehicleModalLabel{{ $vehicle->id }}">Edit Vehicle</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            @include('people::customers.partials.vehicle-form', ['vehicle' => $vehicle])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
@endsection

<?php

namespace Modules\People\Http\Controllers;

use Modules\People\DataTables\CustomersDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\People\Entities\CustomerVehicle;

class CustomersController extends Controller
{

    public function index(CustomersDataTable $dataTable) {
        abort_if(Gate::denies('access_customers'), 403);

        return $dataTable->render('people::customers.index');
    }


    public function create() {
        abort_if(Gate::denies('create_customers'), 403);

        return view('people::customers.create');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('create_customers'), 403);

        $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'nullable|max:255',
            'customer_email' => 'nullable|email|max:255',
            'city'           => 'nullable|string|max:255',
            'country'        => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:500',
        ]);

        Customer::create([
            'customer_name'  => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_email' => $request->customer_email,
            'city'           => $request->city,
            'country'        => $request->country,
            'address'        => $request->address
        ]);

        toast('Customer Created!', 'success');

        return redirect()->route('customers.index');
    }


    public function show(Customer $customer) {
        abort_if(Gate::denies('show_customers'), 403);

        $activeBranch = session('active_branch');
        $vehicles = $customer->vehicles()
            ->when(is_numeric($activeBranch), function ($query) use ($activeBranch) {
                $query->where(function ($q) use ($activeBranch) {
                    $q->whereNull('branch_id')
                        ->orWhere('branch_id', (int) $activeBranch);
                });
            })
            ->latest()
            ->get();

        return view('people::customers.show', compact('customer', 'vehicles'));
    }


    public function edit(Customer $customer) {
        abort_if(Gate::denies('edit_customers'), 403);

        return view('people::customers.edit', compact('customer'));
    }


    public function update(Request $request, Customer $customer) {
        abort_if(Gate::denies('update_customers'), 403);

        $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'nullable|max:255',
            'customer_email' => 'nullable|email|max:255',
            'city'           => 'nullable|string|max:255',
            'country'        => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:500',
        ]);

        $customer->update([
            'customer_name'  => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_email' => $request->customer_email,
            'city'           => $request->city,
            'country'        => $request->country,
            'address'        => $request->address
        ]);

        toast('Customer Updated!', 'info');

        return redirect()->route('customers.index');
    }


    public function destroy(Customer $customer) {
        abort_if(Gate::denies('delete_customers'), 403);

        $customer->delete();

        toast('Customer Deleted!', 'warning');

        return redirect()->route('customers.index');
    }

    public function storeVehicle(Request $request, Customer $customer)
    {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureCustomerIsAccessible($customer);

        $validated = $request->validate($this->vehicleRules());

        $customer->vehicles()->create([
            'branch_id' => $this->resolveVehicleBranchId($customer),
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'car_plate' => $validated['car_plate'],
            'chassis_number' => $validated['chassis_number'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        toast('Vehicle Created!', 'success');

        return redirect()->route('customers.show', $customer);
    }

    public function updateVehicle(Request $request, Customer $customer, CustomerVehicle $vehicle)
    {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureVehicleBelongsToCustomer($customer, $vehicle);

        $validated = $request->validate($this->vehicleRules());

        $vehicle->update([
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'car_plate' => $validated['car_plate'],
            'chassis_number' => $validated['chassis_number'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        toast('Vehicle Updated!', 'info');

        return redirect()->route('customers.show', $customer);
    }

    public function destroyVehicle(Customer $customer, CustomerVehicle $vehicle)
    {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureVehicleBelongsToCustomer($customer, $vehicle);

        $vehicle->delete();

        toast('Vehicle Deleted!', 'warning');

        return redirect()->route('customers.show', $customer);
    }

    private function vehicleRules(): array
    {
        return [
            'vehicle_name' => 'nullable|string|max:255',
            'car_plate' => 'required|string|max:255',
            'chassis_number' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }

    private function ensureVehicleBelongsToCustomer(Customer $customer, CustomerVehicle $vehicle): void
    {
        $this->ensureCustomerIsAccessible($customer);

        abort_if((int) $vehicle->customer_id !== (int) $customer->id, 404);

        $activeBranch = session('active_branch');
        if (is_numeric($activeBranch)) {
            abort_if(
                !is_null($vehicle->branch_id) && (int) $vehicle->branch_id !== (int) $activeBranch,
                403
            );
        }
    }

    private function ensureCustomerIsAccessible(Customer $customer): void
    {
        $activeBranch = session('active_branch');
        if (is_numeric($activeBranch)) {
            abort_if(
                !is_null($customer->branch_id) && (int) $customer->branch_id !== (int) $activeBranch,
                403
            );
        }
    }

    private function resolveVehicleBranchId(Customer $customer): ?int
    {
        $activeBranch = session('active_branch');
        if (is_numeric($activeBranch)) {
            return (int) $activeBranch;
        }

        return !is_null($customer->branch_id) ? (int) $customer->branch_id : null;
    }
}

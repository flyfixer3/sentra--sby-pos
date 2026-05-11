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

        $active = session('active_branch');
        if ($active === 'all' || empty($active) || !is_numeric($active)) {
            return redirect()
                ->route('customers.index')
                ->with('error', 'Please select a specific branch before creating a customer.');
        }

        return view('people::customers.create');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('create_customers'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active) || !is_numeric($active)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please select a specific branch before creating a customer.');
        }

        $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'nullable|max:255',
            'customer_email' => 'nullable|email|max:255',
            'city'           => 'nullable|string|max:255',
            'country'        => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:500',
        ]);

        Customer::create([
            'branch_id'      => (int) $active,
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
        $this->ensureCustomerIsAccessible($customer);

        $vehicles = $this->getCustomerVehicles($customer);

        return view('people::customers.show', compact('customer', 'vehicles'));
    }


    public function edit(Customer $customer) {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureCustomerIsAccessible($customer);

        return view('people::customers.edit', compact('customer'));
    }


    public function update(Request $request, Customer $customer) {
        abort_if(Gate::denies('update_customers'), 403);
        $this->ensureCustomerIsAccessible($customer);

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
        $this->ensureCustomerIsAccessible($customer);

        $customer->delete();

        toast('Customer Deleted!', 'warning');

        return redirect()->route('customers.index');
    }

    public function storeVehicle(Request $request, Customer $customer)
    {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureCustomerIsAccessible($customer);

        $active = session('active_branch');
        if ($active === 'all' || empty($active) || !is_numeric($active)) {
            return redirect()
                ->route('customers.show', $customer)
                ->with('error', 'Please select a specific branch before adding a vehicle.');
        }

        if (is_null($customer->branch_id)) {
            return redirect()
                ->route('customers.show', $customer)
                ->with('error', 'This customer has no branch assigned. Please assign a branch before adding a vehicle.');
        }

        $validated = $request->validate($this->vehicleRules());

        $customer->vehicles()->create([
            'branch_id' => (int) $customer->branch_id,
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'car_plate' => $validated['car_plate'],
            'chassis_number' => $validated['chassis_number'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        toast('Vehicle Created!', 'success');

        return redirect()->route('customers.show', $customer);
    }

    public function vehiclesJson(Customer $customer)
    {
        abort_if(Gate::denies('access_customers'), 403);
        $this->ensureCustomerIsAccessible($customer);

        $vehicles = $this->getCustomerVehicles($customer)
            ->map(function ($vehicle) {
                return [
                    'id' => (int) $vehicle->id,
                    'label' => $this->formatVehicleLabel($vehicle),
                ];
            })
            ->values();

        return response()->json([
            'vehicles' => $vehicles,
        ]);
    }

    public function search(Request $request)
    {
        abort_if(Gate::denies('access_customers'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active) || !is_numeric($active)) {
            return response()->json(['results' => []]);
        }

        $term = trim((string) $request->get('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $customers = Customer::query()
            ->where(function ($query) use ($active) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $active);
            })
            ->where(function ($query) use ($term) {
                $like = '%' . $term . '%';
                $query->where('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_email', 'like', $like);
            })
            ->orderBy('customer_name')
            ->limit(20)
            ->get(['id', 'customer_name', 'customer_phone', 'customer_email']);

        $results = $customers->map(function ($customer) {
            return [
                'id' => (int) $customer->id,
                'text' => $this->formatCustomerLabel($customer),
                'name' => $customer->customer_name,
                'phone' => $customer->customer_phone,
                'email' => $customer->customer_email,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    public function storeVehicleAjax(Request $request, Customer $customer)
    {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureCustomerIsAccessible($customer);

        $active = session('active_branch');
        if ($active === 'all' || empty($active) || !is_numeric($active)) {
            return response()->json([
                'message' => 'Please select a specific branch before adding a vehicle.',
            ], 422);
        }

        if (is_null($customer->branch_id)) {
            return response()->json([
                'message' => 'This customer has no branch assigned. Please assign a branch before adding a vehicle.',
            ], 422);
        }

        $validated = $request->validate($this->vehicleRules());

        $vehicle = $customer->vehicles()->create([
            'branch_id' => (int) $customer->branch_id,
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'car_plate' => $validated['car_plate'],
            'chassis_number' => $validated['chassis_number'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        $vehicles = $this->getCustomerVehicles($customer)
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'label' => $this->formatVehicleLabel($item),
                ];
            })
            ->values();

        $label = $this->formatVehicleLabel($vehicle);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle created.',
            'vehicle' => [
                'id' => (int) $vehicle->id,
                'label' => $label,
                'text' => $label,
            ],
            'vehicles' => $vehicles,
        ]);
    }

    public function updateVehicle(Request $request, Customer $customer, CustomerVehicle $vehicle)
    {
        abort_if(Gate::denies('edit_customers'), 403);
        $this->ensureVehicleBelongsToCustomer($customer, $vehicle);

        $validated = $request->validate($this->vehicleRules());

        $vehicle->update([
            'branch_id' => $customer->branch_id,
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

    private function getCustomerVehicles(Customer $customer)
    {
        $activeBranch = session('active_branch');
        $customerBranchId = $customer->branch_id;

        return $customer->vehicles()
            ->when(!is_null($customerBranchId), function ($query) use ($customerBranchId) {
                $query->where(function ($q) use ($customerBranchId) {
                    $q->whereNull('branch_id')
                        ->orWhere('branch_id', (int) $customerBranchId);
                });
            })
            ->when(is_null($customerBranchId) && is_numeric($activeBranch), function ($query) use ($activeBranch) {
                $query->where(function ($q) use ($activeBranch) {
                    $q->whereNull('branch_id')
                        ->orWhere('branch_id', (int) $activeBranch);
                });
            })
            ->latest()
            ->get();
    }

    private function formatVehicleLabel(CustomerVehicle $vehicle): string
    {
        $label = trim((string) $vehicle->car_plate);
        $vehicleName = trim((string) ($vehicle->vehicle_name ?? ''));

        if ($vehicleName !== '') {
            $label .= ' / ' . $vehicleName;
        }

        return $label !== '' ? $label : ('Vehicle #' . (int) $vehicle->id);
    }

    private function formatCustomerLabel(Customer $customer): string
    {
        $label = trim((string) $customer->customer_name);
        $secondary = trim((string) ($customer->customer_phone ?: $customer->customer_email));

        if ($secondary !== '') {
            $label .= ' - ' . $secondary;
        }

        return $label !== '' ? $label : ('Customer #' . (int) $customer->id);
    }

}

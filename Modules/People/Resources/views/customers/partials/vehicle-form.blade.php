@php
    $vehicle = $vehicle ?? null;
@endphp

<div class="form-group">
    <label for="vehicle_name">Vehicle Name</label>
    <input
        type="text"
        class="form-control"
        name="vehicle_name"
        value="{{ old('vehicle_name', $vehicle->vehicle_name ?? '') }}"
        placeholder="Example: Toyota Avanza"
    >
</div>

<div class="form-group">
    <label for="car_plate">Car Plate <span class="text-danger">*</span></label>
    <input
        type="text"
        class="form-control"
        name="car_plate"
        value="{{ old('car_plate', $vehicle->car_plate ?? '') }}"
        required
        placeholder="Example: B 1234 ABC"
    >
</div>

<div class="form-group">
    <label for="chassis_number">Chassis Number / VIN</label>
    <input
        type="text"
        class="form-control"
        name="chassis_number"
        value="{{ old('chassis_number', $vehicle->chassis_number ?? '') }}"
    >
</div>

<div class="form-group mb-0">
    <label for="note">Note</label>
    <textarea name="note" rows="3" class="form-control">{{ old('note', $vehicle->note ?? '') }}</textarea>
</div>

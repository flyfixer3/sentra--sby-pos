@php
    $sentAt = $data->sent_to_supplier_at ?? null;
    $sentBy = optional($data->sentToSupplierBy)->name;
@endphp

@if($sentAt)
    <div class="d-flex flex-column align-items-center">
        <span class="badge badge-success">Sent to Supplier</span>
        <small class="text-muted mt-1">
            {{ \Carbon\Carbon::parse($sentAt)->format('d-m-Y H:i') }}
            @if($sentBy)
                by {{ $sentBy }}
            @endif
        </small>
    </div>
@else
    <span class="badge badge-secondary">Not Sent</span>
@endif

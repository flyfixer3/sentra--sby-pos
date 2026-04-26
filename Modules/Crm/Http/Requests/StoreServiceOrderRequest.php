<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create_crm_service_orders');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:crm_leads,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address_snapshot' => ['required', 'string', 'max:500'],
            'map_link_snapshot' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}

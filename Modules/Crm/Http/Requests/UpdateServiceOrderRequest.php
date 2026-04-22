<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('edit_crm_service_orders');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address_snapshot' => ['nullable', 'string', 'max:500'],
            'map_link_snapshot' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
        ];
    }
}

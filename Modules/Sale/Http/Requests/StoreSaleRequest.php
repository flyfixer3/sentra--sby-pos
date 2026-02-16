<?php

namespace Modules\Sale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSaleRequest extends FormRequest
{
    public function rules()
    {
        return [
            'customer_id' => 'required|numeric',
            'reference' => 'required|string|max:255',
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percentage'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'required|numeric',
            'payment_method' => 'required|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function authorize()
    {
        return Gate::allows('create_sales');
    }
}

<?php

namespace Modules\Sale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSaleRequest extends FormRequest
{
    public function rules()
    {
        return [
            'customer_id' => 'required|numeric',
            'reference' => 'required|string|max:255',
            'discount_type' => ['required', 'in:fixed,percentage'],
            'header_discount_value' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percentage'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'required|numeric|max:' . $this->sale->total_amount,
            'payment_method' => 'required|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function authorize()
    {
        return Gate::allows('edit_sales');
    }
}

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
            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'required|numeric',

            // ✅ invoice tidak punya status fulfillment lagi
            'status' => 'nullable|string|max:255',

            'payment_method' => 'required|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function authorize()
    {
        return Gate::allows('create_sales');
    }
}

<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePurchaseRequest extends FormRequest
{
    public function rules()
    {
        return [
            'supplier_id' => 'required|numeric',
            'reference' => 'required|string|max:255',

            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'total_quantity'=> 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',

            'status' => 'required|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',

            // supaya createFromDelivery bisa kirim ini tanpa error
            'purchase_order_id' => 'nullable|integer',
            'purchase_delivery_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
        ];
    }

    public function authorize()
    {
        return Gate::allows('create_purchases');
    }
}

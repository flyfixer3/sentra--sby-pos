<?php

namespace Modules\PurchaseOrder\Http\Requests;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdatePurchaseOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        normalize_currency_request($this, [
            'shipping_amount',
            'total_amount',
            'discount_amount',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'supplier_id' => 'required|numeric',
            'reference' => 'required|string|max:255',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'status' => 'required|string|max:255',
            'note' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('edit_purchase_orders');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Cart::instance('purchase_order')->content()->contains(function ($item) {
                return (int) ($item->id ?? 0) > 0 && (int) ($item->qty ?? 0) > 0;
            })) {
                return;
            }

            $validator->errors()->add('products', 'Please add at least one product before submitting this purchase order.');
        });
    }
}

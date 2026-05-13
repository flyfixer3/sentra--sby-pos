<?php

namespace Modules\Sale\Http\Requests;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        normalize_currency_request($this, [
            'shipping_amount',
            'fee_amount',
            'total_amount',
            'paid_amount',
        ]);

        if ($this->input('discount_type') === 'fixed') {
            normalize_currency_request($this, ['header_discount_value']);
        }
    }

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
            'paid_amount' => 'required|numeric',
            'payment_method' => 'required|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function authorize()
    {
        return Gate::allows('create_sales');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Cart::instance('sale')->content()->contains(function ($item) {
                return (int) ($item->id ?? 0) > 0 && (int) ($item->qty ?? 0) > 0;
            })) {
                return;
            }

            $validator->errors()->add('products', 'Please add at least one product before submitting this sale.');
        });
    }
}

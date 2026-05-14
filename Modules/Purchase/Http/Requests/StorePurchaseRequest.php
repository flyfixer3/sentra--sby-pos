<?php

namespace Modules\Purchase\Http\Requests;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        normalize_currency_request($this, [
            'shipping_amount',
            'total_amount',
            'paid_amount',
            'discount_amount',
        ]);
    }

    public function rules()
    {
        return [
            'supplier_id' => 'required|numeric',
            'reference' => 'required|string|max:255',

            'tax_percentage' => 'required|numeric|min:0|max:100',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'shipping_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'total_quantity'=> 'required|numeric|min:1',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->hasValidPurchaseItems()) {
                return;
            }

            $validator->errors()->add(
                'products',
                'Please add at least one product before submitting this purchase.'
            );
        });
    }

    private function hasValidPurchaseItems(): bool
    {
        return Cart::instance('purchase')
            ->content()
            ->contains(function ($item) {
                return (int) ($item->id ?? 0) > 0 && (int) ($item->qty ?? 0) > 0;
            });
    }
}

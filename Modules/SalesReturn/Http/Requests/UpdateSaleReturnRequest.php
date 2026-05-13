<?php

namespace Modules\SalesReturn\Http\Requests;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateSaleReturnRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        normalize_currency_request($this, [
            'shipping_amount',
            'total_amount',
            'paid_amount',
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
            'customer_id' => 'required|numeric',
            'reference' => 'required|string|max:255',
            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'required|numeric|max:' . $this->sale_return->total_amount,
            'status' => 'required|string|max:255',
            'payment_method' => 'required|string|max:255',
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
        return Gate::allows('edit_sale_returns');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Cart::instance('sale_return')->content()->contains(function ($item) {
                return (int) ($item->id ?? 0) > 0 && (int) ($item->qty ?? 0) > 0;
            })) {
                return;
            }

            $validator->errors()->add('products', 'Please add at least one product before submitting this sale return.');
        });
    }
}

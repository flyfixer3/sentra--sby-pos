<?php

namespace Modules\Quotation\Http\Requests;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateQuotationRequest extends FormRequest
{
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
            'status' => 'required|string|in:pending,completed',
            'note' => 'nullable|string|max:1000'
        ];
    }

    protected function prepareForValidation(): void
    {
        normalize_currency_request($this, [
            'shipping_amount',
            'total_amount',
            'discount_amount',
        ]);

        $status = strtolower(trim((string) $this->input('status', '')));

        if ($status === 'sent') {
            $status = 'completed';
        }

        if ($status !== '') {
            $this->merge(['status' => $status]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('edit_quotations');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Cart::instance('quotation')->content()->contains(function ($item) {
                return (int) ($item->id ?? 0) > 0 && (int) ($item->qty ?? 0) > 0;
            })) {
                return;
            }

            $validator->errors()->add('products', 'Please add at least one product before submitting this quotation.');
        });
    }
}

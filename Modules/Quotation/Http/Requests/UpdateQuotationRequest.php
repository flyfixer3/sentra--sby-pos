<?php

namespace Modules\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
}

<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        normalize_currency_request($this, [
            'product_cost',
            'product_price',
            'product_price_item_only',
            'installation_service_price',
            'product_price_package',
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
            'product_name' => ['required', 'string', 'max:255'],
            'product_code' => ['required', 'string', 'max:255', 'unique:products,product_code'],
            'product_barcode_symbology' => ['required', 'string', 'max:255'],
            'product_unit' => ['required', 'string', 'max:255'],
            'product_cost' => ['required', 'numeric', 'max:2147483647'],
            'product_price' => ['required', 'numeric', 'max:2147483647'],
            'product_price_item_only' => ['nullable', 'numeric', 'max:2147483647'],
            'installation_service_price' => ['nullable', 'numeric', 'max:2147483647'],
            'product_price_package' => ['nullable', 'numeric', 'max:2147483647'],
            'product_stock_alert' => ['required', 'integer', 'min:-1'],
            'product_order_tax' => ['nullable', 'integer', 'min:0', 'max:100'],
            'product_tax_type' => ['nullable', 'integer'],
            'product_note' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['required', 'integer'],
            'accessory_ids' => ['nullable', 'array', 'min:1'],
            'accessory_ids.*' => ['integer', 'exists:accessories,id'],
            'accessory_code' => ['nullable', 'string','max:255']
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create_products');
    }
}

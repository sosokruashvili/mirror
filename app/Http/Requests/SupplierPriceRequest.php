<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->route('id');
        $supplierId = $this->input('supplier_id');

        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('supplier_prices', 'product_id')
                    ->where('supplier_id', $supplierId)
                    ->ignore($id),
            ],
            'price_usd' => 'required|numeric|min:0|max:99999999.99',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'supplier_id' => __('supplier_price.attributes.supplier'),
            'product_id' => __('supplier_price.attributes.product'),
            'price_usd' => __('supplier_price.attributes.price_usd'),
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'supplier_id.required' => __('supplier_price.messages.supplier_required'),
            'product_id.required' => __('supplier_price.messages.product_required'),
            'product_id.unique' => __('supplier_price.messages.product_unique'),
            'price_usd.required' => __('supplier_price.messages.price_required'),
            'price_usd.numeric' => __('supplier_price.messages.price_numeric'),
            'price_usd.min' => __('supplier_price.messages.price_min'),
        ];
    }
}

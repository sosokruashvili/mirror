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
            'supplier_id' => 'supplier',
            'product_id' => 'product',
            'price_usd' => 'purchase price (USD)',
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
            'supplier_id.required' => 'Please select a supplier.',
            'product_id.required' => 'Please select a product.',
            'product_id.unique' => 'This supplier already has a price for this product.',
            'price_usd.required' => 'Please enter a purchase price.',
            'price_usd.numeric' => 'Purchase price must be a valid number.',
            'price_usd.min' => 'Purchase price must be at least 0.',
        ];
    }
}

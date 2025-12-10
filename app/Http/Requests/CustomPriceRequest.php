<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomPriceRequest extends FormRequest
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
        $clientId = $this->input('client_id');
        
        return [
            'client_id' => 'required|exists:clients,id',
            'product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('custom_prices', 'product_id')
                    ->where('client_id', $clientId)
                    ->ignore($id),
            ],
            'price_usd' => 'required|numeric|min:0|max:999999.99',
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
            'client_id' => 'client',
            'product_id' => 'product',
            'price_usd' => 'price (USD)',
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
            'client_id.required' => 'Please select a client.',
            'product_id.required' => 'Please select a product.',
            'product_id.unique' => 'This client already has a custom price for this product.',
            'price_usd.required' => 'Please enter a price.',
            'price_usd.numeric' => 'Price must be a valid number.',
            'price_usd.min' => 'Price must be at least 0.',
        ];
    }
}

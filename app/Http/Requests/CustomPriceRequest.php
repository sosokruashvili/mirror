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
            'client_id' => __('custom_price.attributes.client'),
            'product_id' => __('custom_price.attributes.product'),
            'price_usd' => __('custom_price.attributes.price_usd'),
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
            'client_id.required' => __('custom_price.messages.client_required'),
            'product_id.required' => __('custom_price.messages.product_required'),
            'product_id.unique' => __('custom_price.messages.product_unique'),
            'price_usd.required' => __('custom_price.messages.price_required'),
            'price_usd.numeric' => __('custom_price.messages.price_numeric'),
            'price_usd.min' => __('custom_price.messages.price_min'),
        ];
    }
}

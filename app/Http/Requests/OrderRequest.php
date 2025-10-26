<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
        return [
            'order_type' => 'required|in:retail,wholesale',
            'client_id' => 'required|exists:clients,id',
            'status' => 'required|in:draft,new,working,done,finished',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'pieces' => 'required|array|min:1',
            'pieces.*.width' => 'required|numeric|min:0',
            'pieces.*.height' => 'required|numeric|min:0',
            'pieces.*.quantity' => 'required|integer|min:1',
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
            'order_type' => 'order type',
            'products.*.product_id' => 'product',
            'pieces.*.width' => 'width',
            'pieces.*.height' => 'height',
            'pieces.*.quantity' => 'quantity',
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
            'client_id.required' => 'Please select a client for this order.',
            'products.required' => 'At least one product is required.',
            'products.min' => 'At least one product is required.',
            'pieces.required' => 'At least one piece is required.',
            'pieces.min' => 'At least one piece is required.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'description' => 'nullable|string|max:5000',
            'quantity' => 'nullable|integer|min:0',
            'area' => 'nullable|numeric|min:0',
            'file' => 'nullable|file|mimes:pdf,png,jpeg,jpg|max:10240',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'description' => 'description',
            'quantity' => 'quantity',
            'area' => 'area',
            'file' => 'file',
        ];
    }
}

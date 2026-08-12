<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'legal_id' => 'nullable|string|max:255',
            'expenseCategories' => 'nullable|array',
            'expenseCategories.*' => 'exists:expense_categories,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('supplier.attributes.name'),
            'description' => __('supplier.attributes.description'),
            'email' => __('supplier.attributes.email'),
            'address' => __('supplier.attributes.address'),
            'phone' => __('supplier.attributes.phone'),
            'legal_id' => __('supplier.attributes.legal_id'),
            'expenseCategories' => __('supplier.attributes.expense_categories'),
        ];
    }
}

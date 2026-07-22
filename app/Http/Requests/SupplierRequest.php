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
            'name' => 'name',
            'description' => 'description',
            'email' => 'email',
            'address' => 'address',
            'phone' => 'phone',
            'legal_id' => 'legal ID',
            'expenseCategories' => 'expense categories',
        ];
    }
}

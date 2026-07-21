<?php

namespace App\Http\Requests;

use App\Models\CashierExpense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashierExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(CashierExpense::types()))],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id'),
            ],
            'amount_gel' => 'required|numeric|min:0.01|max:999999999.99',
            'description' => 'nullable|string|max:5000',
            'expense_date' => 'required|date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $categoryId = $this->input('category_id');
            if (! $categoryId) {
                return;
            }

            $category = ExpenseCategory::find($categoryId);
            if (! $category || $category->rgt !== $category->lft + 1) {
                $validator->errors()->add('category_id', 'Please select a leaf category (one without child categories).');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'type' => 'type',
            'category_id' => 'category',
            'amount_gel' => 'amount (GEL)',
            'description' => 'description',
            'expense_date' => 'expense date',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\CashierExpense;
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
            'category' => ['required', Rule::in(array_keys(CashierExpense::categories()))],
            'amount_gel' => 'required|numeric|min:0.01|max:999999999.99',
            'description' => 'nullable|string|max:5000',
            'expense_date' => 'required|date',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'type',
            'category' => 'category',
            'amount_gel' => 'amount (GEL)',
            'description' => 'description',
            'expense_date' => 'expense date',
        ];
    }
}

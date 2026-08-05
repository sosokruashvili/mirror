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
            'supplier_id' => 'nullable|exists:suppliers,id',
            'amount_gel' => 'required|numeric|min:0.01|max:999999999.99',
            'credit' => 'nullable|numeric|min:0|max:999999999.99',
            'description' => 'nullable|string|max:5000',
            'file' => 'nullable|file|mimes:pdf,png,jpeg,jpg|max:10240',
            'expense_date' => 'required|date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $categoryId = $this->input('category_id');
            if ($categoryId) {
                $category = ExpenseCategory::find($categoryId);
                if (! $category || $category->rgt !== $category->lft + 1) {
                    $validator->errors()->add('category_id', 'Please select a leaf category (one without child categories).');
                }
            }

            $supplierId = $this->input('supplier_id');
            if ($supplierId && ! $this->supplierIsAllowed((int) $supplierId, $categoryId ? (int) $categoryId : null)) {
                $validator->errors()->add('supplier_id', 'The selected supplier is not linked to this category.');
            }

            $amount = $this->input('amount_gel');
            $credit = $this->input('credit');
            if ($amount !== null && $credit !== null && is_numeric($amount) && is_numeric($credit)
                && (float) $credit > (float) $amount) {
                $validator->errors()->add('credit', 'Credit cannot exceed the full amount.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('credit') === null || $this->input('credit') === '') {
            $this->merge(['credit' => 0]);
        }

        // The supplier field is hidden for categories with no suppliers, so drop
        // any stale value the browser may still have submitted.
        $categoryId = $this->input('category_id');
        $supplierId = $this->input('supplier_id');
        if (ExpenseCategory::supplierIdsFor($categoryId ? (int) $categoryId : null) === []
            && (! $supplierId || ! $this->supplierIsAllowed((int) $supplierId, $categoryId ? (int) $categoryId : null))) {
            $this->merge(['supplier_id' => null]);
        }
    }

    /**
     * A supplier is allowed when it is linked to the category, or when it is the
     * value already stored on the row being edited (links can change over time).
     */
    protected function supplierIsAllowed(int $supplierId, ?int $categoryId): bool
    {
        if (in_array($supplierId, ExpenseCategory::supplierIdsFor($categoryId), true)) {
            return true;
        }

        return $supplierId === (int) $this->storedSupplierId();
    }

    protected function storedSupplierId(): ?int
    {
        $id = $this->input('id') ?? $this->route('id');

        if (! $id) {
            return null;
        }

        $supplierId = CashierExpense::query()->whereKey($id)->value('supplier_id');

        return $supplierId !== null ? (int) $supplierId : null;
    }

    public function attributes(): array
    {
        return [
            'type' => 'type',
            'category_id' => 'category',
            'supplier_id' => 'supplier',
            'amount_gel' => 'amount (GEL)',
            'credit' => 'credit',
            'description' => 'description',
            'file' => 'file',
            'expense_date' => 'expense date',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\CashierExpense;
use App\Models\ExpenseCategory;
use App\Models\SupplierPrice;
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
            'product_id' => 'nullable|exists:products,id',
            'price_usd' => 'nullable|numeric|min:0|max:99999999.99',
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

        // Same for the product field, which is only shown for საწარმოო categories.
        if (! ExpenseCategory::isProductionCategory($categoryId ? (int) $categoryId : null)) {
            $this->merge([
                'product_id' => null,
                'price_usd' => null,
            ]);
        } elseif (($this->input('price_usd') === null || $this->input('price_usd') === '')
            && $this->input('supplier_id')
            && $this->input('product_id')) {
            // Server-side fallback when JavaScript is unavailable.
            $price = SupplierPrice::query()
                ->where('supplier_id', $this->input('supplier_id'))
                ->where('product_id', $this->input('product_id'))
                ->value('price_usd');

            if ($price !== null) {
                $this->merge(['price_usd' => $price]);
            }
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
            'product_id' => 'product',
            'price_usd' => 'purchase price (USD)',
            'amount_gel' => 'amount (GEL)',
            'credit' => 'credit',
            'description' => 'description',
            'file' => 'file',
            'expense_date' => 'expense date',
        ];
    }
}

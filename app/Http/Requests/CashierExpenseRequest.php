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
            'status' => ['required', Rule::in(array_keys(CashierExpense::statuses()))],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id'),
            ],
            'supplier_id' => 'nullable|exists:suppliers,id',
            'product_id' => 'nullable|exists:products,id',
            'price_usd' => 'nullable|numeric|min:0|max:99999999.99',
            'amount_gel' => 'required|numeric|min:0|max:999999999.99',
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
                    $validator->errors()->add('category_id', __('cashier_expense.messages.category_not_leaf'));
                }
            }

            $supplierId = $this->input('supplier_id');
            if ($supplierId && ! $this->supplierIsAllowed((int) $supplierId, $categoryId ? (int) $categoryId : null)) {
                $validator->errors()->add('supplier_id', __('cashier_expense.messages.supplier_not_linked'));
            }

            $amount = $this->input('amount_gel');
            $credit = $this->input('credit');
            if (is_numeric($amount) && is_numeric($credit)
                && (float) $amount + (float) $credit <= 0) {
                $validator->errors()->add('amount_gel', __('cashier_expense.messages.amount_or_credit_required'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('credit') === null || $this->input('credit') === '') {
            $this->merge(['credit' => 0]);
        }

        if ($this->input('amount_gel') === null || $this->input('amount_gel') === '') {
            $this->merge(['amount_gel' => 0]);
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
            'type' => __('cashier_expense.attributes.type'),
            'status' => __('cashier_expense.attributes.status'),
            'category_id' => __('cashier_expense.attributes.category'),
            'supplier_id' => __('cashier_expense.attributes.supplier'),
            'product_id' => __('cashier_expense.attributes.product'),
            'price_usd' => __('cashier_expense.attributes.price_usd'),
            'amount_gel' => __('cashier_expense.attributes.amount_gel'),
            'credit' => __('cashier_expense.attributes.credit'),
            'description' => __('cashier_expense.attributes.description'),
            'file' => __('cashier_expense.attributes.file'),
            'expense_date' => __('cashier_expense.attributes.expense_date'),
        ];
    }
}

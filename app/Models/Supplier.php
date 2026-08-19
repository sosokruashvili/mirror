<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use CrudTrait;

    protected $fillable = [
        'name',
        'description',
        'email',
        'address',
        'phone',
        'legal_id',
    ];

    public function cashierExpenses(): HasMany
    {
        return $this->hasMany(CashierExpense::class);
    }

    /**
     * The expenses that count towards this supplier's balance. Drafts are still
     * being entered, so they are excluded everywhere a balance is computed.
     */
    public function confirmedCashierExpenses(): HasMany
    {
        return $this->hasMany(CashierExpense::class)
            ->where('status', CashierExpense::STATUS_CONFIRMED);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function supplierPrices(): HasMany
    {
        return $this->hasMany(SupplierPrice::class);
    }

    public function expenseCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpenseCategory::class,
            'supplier_expense_category'
        );
    }
}

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
    ];

    public function cashierExpenses(): HasMany
    {
        return $this->hasMany(CashierExpense::class);
    }

    public function expenseCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpenseCategory::class,
            'supplier_expense_category'
        );
    }
}

<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class CashierExpense extends Model
{
    use CrudTrait;

    public const TYPE_CASH = 'Cash';
    public const TYPE_TRANSFER = 'Transfer';

    public const CATEGORY_FOOD = 'Food';
    public const CATEGORY_ACCESSORIES = 'Accessories';
    public const CATEGORY_CONSUMABLE_MATERIALS = 'Consumable Materials';
    public const CATEGORY_INSTALLATION = 'Installation';
    public const CATEGORY_SALARY = 'Salary';

    protected $fillable = [
        'type',
        'category',
        'amount_gel',
        'description',
        'expense_date',
    ];

    protected $casts = [
        'amount_gel' => 'decimal:2',
        'expense_date' => 'datetime',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_CASH => 'Cash',
            self::TYPE_TRANSFER => 'Transfer',
        ];
    }

    public static function categories(): array
    {
        return [
            self::CATEGORY_FOOD => 'Food',
            self::CATEGORY_ACCESSORIES => 'Accessories',
            self::CATEGORY_CONSUMABLE_MATERIALS => 'Consumable Materials',
            self::CATEGORY_INSTALLATION => 'Installation',
            self::CATEGORY_SALARY => 'Salary',
        ];
    }
}

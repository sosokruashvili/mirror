<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierExpense extends Model
{
    use CrudTrait;

    public const TYPE_CASH = 'Cash';
    public const TYPE_TRANSFER = 'Transfer';

    protected $fillable = [
        'type',
        'category_id',
        'amount_gel',
        'description',
        'expense_date',
    ];

    protected $casts = [
        'amount_gel' => 'decimal:2',
        'expense_date' => 'datetime',
        'category_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public static function types(): array
    {
        return [
            self::TYPE_CASH => 'Cash',
            self::TYPE_TRANSFER => 'Transfer',
        ];
    }
}

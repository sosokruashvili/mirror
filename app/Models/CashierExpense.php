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
        'supplier_id',
        'amount_gel',
        'description',
        'file',
        'expense_date',
    ];

    protected $casts = [
        'amount_gel' => 'decimal:2',
        'expense_date' => 'datetime',
        'category_id' => 'integer',
        'supplier_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function setFileAttribute($value)
    {
        $attributeName = 'file';
        $disk = 'public';
        $destinationPath = 'cashier-expenses';

        $this->uploadFileToDisk($value, $attributeName, $disk, $destinationPath);
    }

    public static function types(): array
    {
        return [
            self::TYPE_CASH => 'Cash',
            self::TYPE_TRANSFER => 'Transfer',
        ];
    }
}

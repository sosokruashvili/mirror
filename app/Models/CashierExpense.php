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
    public const TYPE_PM_TRANSFER = 'PM Transfer';

    protected $fillable = [
        'type',
        'category_id',
        'supplier_id',
        'product_id',
        'price_usd',
        'amount_gel',
        'credit',
        'description',
        'file',
        'expense_date',
    ];

    protected $casts = [
        'amount_gel' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'credit' => 'decimal:2',
        'expense_date' => 'datetime',
        'category_id' => 'integer',
        'supplier_id' => 'integer',
        'product_id' => 'integer',
    ];

    /**
     * Amount actually paid now (full price minus credit).
     */
    public function getPaidAmountAttribute(): float
    {
        return round((float) $this->amount_gel - (float) $this->credit, 2);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
        // Keys are the values stored in cashier_expenses.type and must not
        // change; the labels are shared with payments.method, so they come
        // from the one place those are translated.
        return [
            self::TYPE_CASH => __('payment.methods.' . self::TYPE_CASH),
            self::TYPE_TRANSFER => __('payment.methods.' . self::TYPE_TRANSFER),
            self::TYPE_PM_TRANSFER => __('payment.methods.' . self::TYPE_PM_TRANSFER),
        ];
    }
}

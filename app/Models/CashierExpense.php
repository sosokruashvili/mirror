<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierExpense extends Model
{
    use CrudTrait;

    public const TYPE_CASH = 'Cash';
    public const TYPE_TRANSFER = 'Transfer';
    public const TYPE_PM_TRANSFER = 'PM Transfer';

    /**
     * A draft expense is still being entered: it is editable and it counts
     * nowhere - not in the cashier balance, not in the supplier balances, not
     * in the list summary widget. Confirming it freezes the row and lets it
     * into every calculation.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'type',
        'status',
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
     * Only the expenses that count: everywhere an expense feeds a balance, a
     * total or a report, the query goes through this scope.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Confirmed expenses are frozen: they are already part of the cashier and
     * supplier balances, so nobody edits them. Drafts are editable by anyone
     * who otherwise holds update access.
     *
     * This encodes only the status rule; whether the user holds the
     * "cashier-expense.update" capability at all is enforced separately by the
     * CRUD access checks.
     */
    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isDraft();
    }

    /**
     * Mirrors the edit rule, with an escape hatch: a confirmed expense can only
     * be removed by an administrator, because deleting it silently rewrites the
     * cashier and supplier balances. Drafts count nowhere, so deleting one is
     * free.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isDraft() || $user->isSuperAdmin();
    }

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

    public static function statuses(): array
    {
        // Keys are the values stored in cashier_expenses.status and must not
        // change; the labels come from the shared, value-keyed status file.
        return [
            self::STATUS_DRAFT => __('status.' . self::STATUS_DRAFT),
            self::STATUS_CONFIRMED => __('status.' . self::STATUS_CONFIRMED),
        ];
    }
}

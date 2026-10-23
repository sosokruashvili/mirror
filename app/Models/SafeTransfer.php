<?php

namespace App\Models;

use App\Services\CashierService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafeTransfer extends Model
{
    use CrudTrait;

    public const DIRECTION_TO_SAFE = 'to_safe';
    public const DIRECTION_TO_CASHIER = 'to_cashier';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'direction',
        'status',
        'amount_gel',
        'description',
        'transferred_at',
    ];

    protected $casts = [
        'amount_gel' => 'decimal:2',
        'transferred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SafeTransfer $transfer) {
            if ($transfer->author === null && function_exists('backpack_user') && backpack_user()) {
                $transfer->author = backpack_user()->id;
            }
        });

        static::saved(function (SafeTransfer $transfer) {
            CashierService::refreshFromTransferChange($transfer);
        });

        static::deleted(function (SafeTransfer $transfer) {
            CashierService::refreshFromTransferChange($transfer);
        });
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
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
     * Confirmed transfers are already inside the cashier and safe balances.
     */
    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isDraft();
    }

    /**
     * Deleting a confirmed transfer puts the money back. Only an administrator
     * may do that. Drafts have not moved any money.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isDraft() || $user->isSuperAdmin();
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author');
    }

    public static function directions(): array
    {
        return [
            self::DIRECTION_TO_SAFE => __('safe.directions.to_safe'),
            self::DIRECTION_TO_CASHIER => __('safe.directions.to_cashier'),
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => __('status.' . self::STATUS_DRAFT),
            self::STATUS_CONFIRMED => __('status.' . self::STATUS_CONFIRMED),
        ];
    }

    public function directionLabel(): string
    {
        return self::directions()[$this->direction] ?? $this->direction;
    }
}

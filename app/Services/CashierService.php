<?php

namespace App\Services;

use App\Models\CashierBalance;
use App\Models\CashierExpense;
use App\Models\Payment;
use App\Models\SafeTransfer;
use Carbon\Carbon;

class CashierService
{
    /**
     * Query for the payments that count as cash-in on a given day.
     * Single source of truth for both the daily sum and the details-row list.
     */
    public function cashPaymentsQueryForDate(Carbon $date)
    {
        return Payment::query()
            ->where('method', 'Cash')
            ->where('status', 'Paid')
            ->whereDate('payment_date', $date);
    }

    /**
     * Query for the expenses that count as cash-out on a given day.
     * Single source of truth for both the daily sum and the details-row list.
     *
     * Draft expenses are still being entered and are deliberately left out of
     * the balance until they are confirmed.
     */
    public function cashExpensesQueryForDate(Carbon $date)
    {
        return CashierExpense::query()
            ->confirmed()
            ->with('category')
            ->where('type', CashierExpense::TYPE_CASH)
            ->where(function ($query) {
                $query->whereNull('source')
                    ->orWhere('source', '!=', CashierExpense::SOURCE_SAFE);
            })
            ->whereDate('expense_date', $date);
    }

    /**
     * Confirmed cash moved from the cashier into the safe on this day.
     */
    public function safeDepositsQueryForDate(Carbon $date)
    {
        return SafeTransfer::query()
            ->confirmed()
            ->with('authorUser')
            ->where('direction', SafeTransfer::DIRECTION_TO_SAFE)
            ->whereDate('transferred_at', $date);
    }

    /**
     * Confirmed cash moved from the safe back into the cashier on this day.
     */
    public function safeReturnsQueryForDate(Carbon $date)
    {
        return SafeTransfer::query()
            ->confirmed()
            ->with('authorUser')
            ->where('direction', SafeTransfer::DIRECTION_TO_CASHIER)
            ->whereDate('transferred_at', $date);
    }

    public function getCashPaymentsForDate(Carbon $date): float
    {
        return (float) $this->cashPaymentsQueryForDate($date)->sum('amount_gel');
    }

    public function getCashExpensesForDate(Carbon $date): float
    {
        return (float) $this->cashExpensesQueryForDate($date)->sum('amount_gel');
    }

    public function getSafeDepositsForDate(Carbon $date): float
    {
        return (float) $this->safeDepositsQueryForDate($date)->sum('amount_gel');
    }

    public function getSafeReturnsForDate(Carbon $date): float
    {
        return (float) $this->safeReturnsQueryForDate($date)->sum('amount_gel');
    }

    /**
     * Money that entered the cashier: cash payments plus cash returned from the safe.
     */
    public function getCashInForDate(Carbon $date): float
    {
        return $this->getCashPaymentsForDate($date) + $this->getSafeReturnsForDate($date);
    }

    /**
     * Money that left the cashier: cash expenses paid from the cashier, plus
     * deposits into the safe. Expenses paid from the safe are not included.
     */
    public function getCashOutForDate(Carbon $date): float
    {
        return $this->getCashExpensesForDate($date) + $this->getSafeDepositsForDate($date);
    }

    public function getNetChangeForDate(Carbon $date): float
    {
        return $this->getCashInForDate($date) - $this->getCashOutForDate($date);
    }

    /**
     * Confirmed safe balance: deposits in, returns and safe-paid expenses out.
     */
    public function getSafeBalance(): float
    {
        $deposited = (float) SafeTransfer::query()
            ->confirmed()
            ->where('direction', SafeTransfer::DIRECTION_TO_SAFE)
            ->sum('amount_gel');

        $returned = (float) SafeTransfer::query()
            ->confirmed()
            ->where('direction', SafeTransfer::DIRECTION_TO_CASHIER)
            ->sum('amount_gel');

        $spent = (float) CashierExpense::query()
            ->confirmed()
            ->where('type', CashierExpense::TYPE_CASH)
            ->where('source', CashierExpense::SOURCE_SAFE)
            ->sum('amount_gel');

        return round($deposited - $returned - $spent, 2);
    }

    public function getSafeStats(): array
    {
        $deposited = (float) SafeTransfer::query()
            ->confirmed()
            ->where('direction', SafeTransfer::DIRECTION_TO_SAFE)
            ->sum('amount_gel');

        $returned = (float) SafeTransfer::query()
            ->confirmed()
            ->where('direction', SafeTransfer::DIRECTION_TO_CASHIER)
            ->sum('amount_gel');

        $expenses = (float) CashierExpense::query()
            ->confirmed()
            ->where('type', CashierExpense::TYPE_CASH)
            ->where('source', CashierExpense::SOURCE_SAFE)
            ->sum('amount_gel');

        return [
            'balance' => round($deposited - $returned - $expenses, 2),
            'deposited' => round($deposited, 2),
            'returned' => round($returned, 2),
            'expenses' => round($expenses, 2),
        ];
    }

    /**
     * Rewrite stored daily snapshots from $date through the latest stored day.
     * Today's live balance does not depend on a snapshot for today.
     */
    public function resnapshotFrom(?Carbon $date): int
    {
        if ($date === null) {
            return 0;
        }

        $first = CashierBalance::min('balance_date');
        $last = CashierBalance::max('balance_date');

        // No stored chain yet: today's balance is computed live, and inventing
        // a first snapshot here would freeze an opening of zero.
        if (! $first || ! $last) {
            return 0;
        }

        $start = $date->copy()->startOfDay();
        $chainStart = Carbon::parse($first)->startOfDay();
        if ($start->lt($chainStart)) {
            $start = $chainStart;
        }

        $end = now()->startOfDay();
        $chainEnd = Carbon::parse($last)->startOfDay();
        if ($chainEnd->gt($end)) {
            $end = $chainEnd;
        }

        if ($start->gt($end)) {
            return 0;
        }

        $count = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $this->snapshotDailyBalance($day);
            $count++;
        }

        return $count;
    }

    /**
     * A confirmed cashier-cash expense, or one that used to be, changes the
     * stored daily chain from the earlier of its old and new dates.
     */
    public static function refreshFromExpenseChange(CashierExpense $expense): void
    {
        $affectedBefore = $expense->getOriginal('status') === CashierExpense::STATUS_CONFIRMED
            && $expense->getOriginal('type') === CashierExpense::TYPE_CASH
            && $expense->getOriginal('source') !== CashierExpense::SOURCE_SAFE;

        $affectedNow = $expense->exists && $expense->isConfirmed() && $expense->isCashierCash();

        if (! $affectedBefore && ! $affectedNow) {
            return;
        }

        app(self::class)->resnapshotFrom(self::earliestDate([
            $expense->expense_date,
            $expense->getOriginal('expense_date'),
        ]));
    }

    /**
     * Any confirmed safe transfer moves cashier cash, in either direction.
     */
    public static function refreshFromTransferChange(SafeTransfer $transfer): void
    {
        $affectedBefore = $transfer->getOriginal('status') === SafeTransfer::STATUS_CONFIRMED;
        $affectedNow = $transfer->exists && $transfer->isConfirmed();

        if (! $affectedBefore && ! $affectedNow) {
            return;
        }

        app(self::class)->resnapshotFrom(self::earliestDate([
            $transfer->transferred_at,
            $transfer->getOriginal('transferred_at'),
        ]));
    }

    /**
     * @param  array<int, mixed>  $dates
     */
    private static function earliestDate(array $dates): ?Carbon
    {
        $earliest = null;

        foreach ($dates as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parsed = Carbon::parse($value);
            if ($earliest === null || $parsed->lt($earliest)) {
                $earliest = $parsed;
            }
        }

        return $earliest;
    }

    public function getPreviousClosingBalance(Carbon $date): float
    {
        $previous = CashierBalance::query()
            ->where('balance_date', '<', $date->toDateString())
            ->orderByDesc('balance_date')
            ->value('amount');

        return (float) ($previous ?? 0);
    }

    public function getClosingBalanceForDate(Carbon $date): float
    {
        return $this->getPreviousClosingBalance($date) + $this->getNetChangeForDate($date);
    }

    public function getCurrentBalance(): float
    {
        return $this->getClosingBalanceForDate(now());
    }

    public function snapshotDailyBalance(?Carbon $date = null): CashierBalance
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $amount = $this->getClosingBalanceForDate($date);

        return CashierBalance::updateOrCreate(
            ['balance_date' => $date->toDateString()],
            ['amount' => $amount]
        );
    }

    /**
     * Re-run every stored snapshot in chronological order, from the earliest
     * to the latest snapshotted day (gaps in between are filled). Each day's
     * closing feeds the next day's opening, so backdated payment/expense edits
     * propagate through the whole chain. Returns the number of days written.
     */
    public function resnapshotAll(): int
    {
        $first = CashierBalance::min('balance_date');
        $last = CashierBalance::max('balance_date');

        if (!$first) {
            return 0;
        }

        $date = Carbon::parse($first)->startOfDay();
        $end = Carbon::parse($last)->startOfDay();
        $count = 0;

        while ($date->lte($end)) {
            $this->snapshotDailyBalance($date);
            $count++;
            $date->addDay();
        }

        return $count;
    }

    public function getTodayStats(): array
    {
        $today = now()->startOfDay();

        return [
            'current_balance' => $this->getCurrentBalance(),
            'opening_balance' => $this->getPreviousClosingBalance($today),
            'cash_in' => $this->getCashInForDate($today),
            'cash_out' => $this->getCashOutForDate($today),
            'net_change' => $this->getNetChangeForDate($today),
        ];
    }
}

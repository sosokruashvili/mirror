<?php

namespace App\Services;

use App\Models\CashierBalance;
use App\Models\CashierExpense;
use App\Models\Payment;
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
     */
    public function cashExpensesQueryForDate(Carbon $date)
    {
        return CashierExpense::query()
            ->with('category')
            ->where('type', CashierExpense::TYPE_CASH)
            ->whereDate('expense_date', $date);
    }

    public function getCashPaymentsForDate(Carbon $date): float
    {
        return (float) $this->cashPaymentsQueryForDate($date)->sum('amount_gel');
    }

    public function getCashExpensesForDate(Carbon $date): float
    {
        return (float) $this->cashExpensesQueryForDate($date)->sum('amount_gel');
    }

    public function getNetChangeForDate(Carbon $date): float
    {
        return $this->getCashPaymentsForDate($date) - $this->getCashExpensesForDate($date);
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
            'cash_in' => $this->getCashPaymentsForDate($today),
            'cash_out' => $this->getCashExpensesForDate($today),
            'net_change' => $this->getNetChangeForDate($today),
        ];
    }
}

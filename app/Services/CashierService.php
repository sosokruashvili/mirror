<?php

namespace App\Services;

use App\Models\CashierBalance;
use App\Models\CashierExpense;
use App\Models\Payment;
use Carbon\Carbon;

class CashierService
{
    public function getCashPaymentsForDate(Carbon $date): float
    {
        return (float) Payment::query()
            ->where('method', 'Cash')
            ->where('status', 'Paid')
            ->whereDate('payment_date', $date)
            ->sum('amount_gel');
    }

    public function getCashExpensesForDate(Carbon $date): float
    {
        return (float) CashierExpense::query()
            ->where('type', CashierExpense::TYPE_CASH)
            ->whereDate('expense_date', $date)
            ->sum('amount_gel');
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

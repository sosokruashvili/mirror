<?php

namespace App\Http\Controllers\Admin;

use App\Models\CashierBalance;
use App\Services\CashierService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

class CashierCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    public function setup(): void
    {
        CRUD::setModel(CashierBalance::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/cashier');
        CRUD::setEntityNameStrings('cashier', 'cashier');

        $this->crud->denyAccess(['create', 'update', 'delete', 'show']);
        $this->crud->enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        $this->addCashierStatsWidget();

        // Manual "recalculate now" button: re-chains every stored snapshot so
        // backdated payments/expenses stop drifting from the frozen amounts.
        $this->crud->addButtonFromView('top', 'recalculate_cashier', 'recalculate_cashier', 'beginning');

        // Expandable rows: clicking a day loads the cash payments and expenses
        // that produced that day's balance. The custom list view makes the whole
        // row (not just the +/- icon) the trigger, same as the client balance page.
        $this->crud->enableDetailsRow();
        $this->crud->setDetailsRowView('vendor.backpack.crud.details_rows.cashier_balance');
        $this->crud->setListView('vendor.backpack.crud.cashier.list');

        CRUD::addColumn([
            'name' => 'balance_date',
            'label' => 'Date',
            'type' => 'date',
        ]);

        CRUD::addColumn([
            'name' => 'amount',
            'label' => 'Closing Balance',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Snapshot At',
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'type' => 'date_range',
            'name' => 'balance_date',
            'label' => 'Date Range',
        ], false, function ($value) {
            $dates = json_decode($value, true);
            if (!empty($dates['from'])) {
                $this->crud->addClause('where', 'balance_date', '>=', $dates['from']);
            }
            if (!empty($dates['to'])) {
                $this->crud->addClause('where', 'balance_date', '<=', $dates['to']);
            }
        });

        $this->crud->orderBy('balance_date', 'desc');
    }

    /**
     * Render the expanded breakdown for one day's snapshot: opening balance,
     * the cash payments (in) and cash expenses (out) of that day, and the
     * resulting closing balance. Called over AJAX by the details-row logic.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showDetailsRow($id)
    {
        $this->crud->hasAccessOrFail('list');

        $entry = CashierBalance::findOrFail($id);
        $date = $entry->balance_date->copy()->startOfDay();
        $service = app(CashierService::class);

        $payments = $service->cashPaymentsQueryForDate($date)
            ->with(['client', 'order'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $expenses = $service->cashExpensesQueryForDate($date)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $openingBalance = $service->getPreviousClosingBalance($date);
        $cashIn = (float) $payments->sum('amount_gel');
        $cashOut = (float) $expenses->sum('amount_gel');

        return view('vendor.backpack.crud.details_rows.cashier_balance', [
            'crud' => $this->crud,
            'entry' => $entry,
            'openingBalance' => $openingBalance,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'netChange' => $cashIn - $cashOut,
            // Recomputed from today's data; can drift from the stored snapshot
            // if payments/expenses were edited after the snapshot was taken.
            'calculatedClosing' => $openingBalance + $cashIn - $cashOut,
            'storedClosing' => (float) $entry->amount,
            'payments' => $payments,
            'expenses' => $expenses,
        ]);
    }

    /**
     * Re-run the full snapshot chain (earliest stored day through the latest),
     * then return fresh today-stats so the widget can update without a reload.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recalculate(CashierService $service)
    {
        $this->crud->hasAccessOrFail('list');

        $count = $service->resnapshotAll();

        return response()->json(array_merge([
            'message' => "Recalculated cashier balance for {$count} day(s).",
        ], $service->getTodayStats()));
    }

    protected function addCashierStatsWidget(): void
    {
        $stats = app(CashierService::class)->getTodayStats();

        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.cashier_stats',
            'wrapper' => ['class' => 'col-12', 'id' => 'cashier-stats-widget'],
            'stats' => $stats,
        ])->to('before_content');
    }
}

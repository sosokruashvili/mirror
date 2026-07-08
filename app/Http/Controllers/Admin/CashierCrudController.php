<?php

namespace App\Http\Controllers\Admin;

use App\Models\CashierBalance;
use App\Services\CashierService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

class CashierCrudController extends CrudController
{
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

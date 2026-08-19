<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CashierExpenseRequest;
use App\Models\CashierExpense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\SupplierPrice;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

class CashierExpenseCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(CashierExpense::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/cashier-expense');
        CRUD::setEntityNameStrings(__('cashier_expense.entity'), __('cashier_expense.entity_plural'));

        $this->crud->enableExportButtons();

        // Confirmed expenses are frozen — they are already part of the cashier
        // and supplier balances. The condition is checked per row, so Backpack
        // hides the Edit/Delete buttons on confirmed rows AND rejects the
        // edit/update/destroy routes for them. A null entry means the check is
        // not about one specific row (e.g. the list page as a whole), so it
        // passes; the ChecksAccess trait still denies the operation outright
        // when the user lacks the page permission, and that runs after this.
        // See CashierExpense::canBeEditedBy() / canBeDeletedBy().
        $this->crud->setAccessCondition('update', function ($entry) {
            return $entry === null || $entry->canBeEditedBy(backpack_user());
        });
        $this->crud->setAccessCondition('delete', function ($entry) {
            return $entry === null || $entry->canBeDeletedBy(backpack_user());
        });
    }

    protected function setupListOperation(): void
    {
        $this->crud->query->with(['category', 'supplier', 'product']);
        $this->addExpenseStatsWidget();

        CRUD::addColumn([
            'name' => 'id',
            'label' => __('cashier_expense.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => __('cashier_expense.status'),
            'type' => 'custom_html',
            'value' => function ($entry) {
                return status_badge($entry->status);
            },
        ]);

        CRUD::addColumn([
            'name' => 'type',
            'label' => __('cashier_expense.type'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'category_id',
            'label' => __('cashier_expense.category'),
            'type' => 'select',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => ExpenseCategory::class,
        ]);

        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => __('cashier_expense.supplier'),
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'name',
            'model' => \App\Models\Supplier::class,
        ]);

        CRUD::addColumn([
            'name' => 'product_id',
            'label' => __('cashier_expense.product'),
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => Product::class,
        ]);

        CRUD::addColumn([
            'name' => 'price_usd',
            'label' => __('cashier_expense.price_usd'),
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        CRUD::addColumn([
            'name' => 'amount_gel',
            'label' => __('cashier_expense.amount_gel'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'credit',
            'label' => __('cashier_expense.credit'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'payment_progress',
            'label' => __('cashier_expense.payment_progress'),
            'type' => 'custom_html',
            'value' => function ($entry) {
                $amount = (float) $entry->amount_gel;
                $credit = max(0, min((float) $entry->credit, $amount));

                if ($amount <= 0) {
                    return '<span class="text-muted">-</span>';
                }

                // Credit is the unpaid portion, so the rest is already paid:
                // green = paid share, red = outstanding credit share.
                $paid = $amount - $credit;
                $paidPercent = $paid / $amount * 100;

                $title = __('cashier_expense.progress_title', [
                    'paid' => number_format($paid, 2),
                    'credit' => number_format($credit, 2),
                    'total' => number_format($amount, 2),
                ]);

                return sprintf(
                    '<div class="d-flex align-items-center" style="min-width: 110px;" title="%s">'
                        . '<div class="progress flex-grow-1 me-2" style="height: 6px; min-width: 60px;">'
                            . '<div class="progress-bar bg-success" role="progressbar" style="width: %s%%;"></div>'
                            . '<div class="progress-bar bg-danger" role="progressbar" style="width: %s%%;"></div>'
                        . '</div>'
                        . '<small class="text-muted">%s%%</small>'
                    . '</div>',
                    htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                    round($paidPercent, 2),
                    round(100 - $paidPercent, 2),
                    round($paidPercent)
                );
            },
            'orderable' => true,
            'orderLogic' => function ($query, $column, $columnDirection) {
                $direction = strtoupper($columnDirection) === 'ASC' ? 'ASC' : 'DESC';

                // Sort by the same paid share the bar draws: (amount - credit) / amount,
                // clamped to 0..1. Rows without an amount have no percentage, so they
                // sort as 0% (and the CASE keeps Postgres from dividing by zero).
                return $query->orderByRaw(
                    'CASE WHEN COALESCE(cashier_expenses.amount_gel, 0) <= 0 THEN 0'
                    . ' ELSE GREATEST(0, LEAST(1, (COALESCE(cashier_expenses.amount_gel, 0) - COALESCE(cashier_expenses.credit, 0))'
                    . ' / COALESCE(cashier_expenses.amount_gel, 0)))'
                    . ' END ' . $direction
                );
            },
            'searchLogic' => false,
            // The bar is markup; the CSV/Excel export already has amount + credit.
            'visibleInExport' => false,
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => __('cashier_expense.description'),
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'file',
            'label' => __('cashier_expense.file'),
            'type' => 'upload',
            'disk' => 'public',
        ]);

        CRUD::addColumn([
            'name' => 'expense_date',
            'label' => __('cashier_expense.expense_date'),
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'name' => 'status',
            'type' => 'select2',
            'label' => __('cashier_expense.status'),
        ], function () {
            return CashierExpense::statuses();
        }, function ($value) {
            $this->crud->addClause('where', 'status', $value);
        });

        CRUD::addFilter([
            'name' => 'type',
            'type' => 'select2',
            'label' => __('cashier_expense.type'),
        ], function () {
            return CashierExpense::types();
        }, function ($value) {
            $this->crud->addClause('where', 'type', $value);
        });

        CRUD::addFilter([
            'name' => 'category_id',
            'type' => 'select2',
            'label' => __('cashier_expense.category'),
        ], function () {
            return ExpenseCategory::filterOptions();
        }, function ($value) {
            $this->applyCategoryFilter($this->crud->query, (int) $value);
        });

        CRUD::addFilter([
            'name' => 'supplier_id',
            'type' => 'select2',
            'label' => __('cashier_expense.supplier'),
        ], function () {
            return \App\Models\Supplier::query()->orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'supplier_id', $value);
        });

        CRUD::addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => __('cashier_expense.product'),
        ], function () {
            return Product::query()->orderBy('title')->pluck('title', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'product_id', $value);
        });

        CRUD::addFilter([
            'type' => 'date_range',
            'name' => 'expense_date',
            'label' => __('cashier_expense.filters.date_range'),
        ], false, function ($value) {
            $dates = json_decode($value, true);
            if (!empty($dates['from'])) {
                $this->crud->addClause('where', 'expense_date', '>=', \Carbon\Carbon::parse($dates['from'])->startOfDay());
            }
            if (!empty($dates['to'])) {
                $this->crud->addClause('where', 'expense_date', '<=', \Carbon\Carbon::parse($dates['to'])->endOfDay());
            }
        });

        $this->crud->orderBy('expense_date', 'desc');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(CashierExpenseRequest::class);

        Widget::add([
            'name' => 'cashier_expense_supplier_config',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.cashier_expense_supplier_config',
            'supplierOptions' => ExpenseCategory::supplierOptionsMap(),
            'productionCategoryIds' => ExpenseCategory::productionCategoryIds(),
            'supplierPrices' => SupplierPrice::priceMap(),
        ]);
        // Filename is versioned so browsers pick up fixes (Basset cache-busts via composer.lock only).
        Widget::add()->type('script')->content('assets/js/cashier-expense-supplier-v4.js');

        CRUD::addField([
            'name' => 'status',
            'label' => __('cashier_expense.status'),
            'type' => 'select_from_array',
            'options' => CashierExpense::statuses(),
            'allows_null' => false,
            'default' => CashierExpense::STATUS_DRAFT,
            'hint' => __('cashier_expense.hints.status'),
        ]);

        CRUD::addField([
            'name' => 'type',
            'label' => __('cashier_expense.type'),
            'type' => 'select_from_array',
            'options' => CashierExpense::types(),
            'allows_null' => false,
            'default' => CashierExpense::TYPE_CASH,
        ]);

        CRUD::addField([
            'name' => 'category_id',
            'label' => __('cashier_expense.category'),
            'type' => 'select_optgroup_array',
            'options' => ExpenseCategory::groupedLeafOptions(),
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'supplier_id',
            'label' => __('cashier_expense.supplier'),
            'type' => 'select',
            'entity' => 'supplier',
            'model' => \App\Models\Supplier::class,
            'attribute' => 'name',
            'options' => (function ($query) {
                return $query->orderBy('name', 'ASC')->get();
            }),
            'allows_null' => true,
            'hint' => __('cashier_expense.hints.supplier'),
            // Hidden until a category that has suppliers is picked (JS toggles d-none).
            'wrapper' => [
                'class' => 'form-group col-sm-12 mb-3 d-none',
            ],
        ]);

        CRUD::addField([
            'name' => 'product_id',
            'label' => __('cashier_expense.product'),
            'type' => 'select2',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => Product::class,
            'allows_null' => true,
            'options' => (function ($query) {
                return $query->orderBy('title')->get();
            }),
            'attributes' => [
                'data-placeholder' => __('cashier_expense.placeholders.product'),
            ],
            'hint' => __('cashier_expense.hints.product'),
            // Hidden until a საწარმოო category is picked (JS toggles d-none).
            'wrapper' => [
                'class' => 'form-group col-sm-12 mb-3 d-none',
            ],
        ]);

        CRUD::addField([
            'name' => 'price_usd',
            'label' => __('cashier_expense.price_usd_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'placeholder' => __('cashier_expense.placeholders.price_usd'),
            ],
            'prefix' => '$',
            'hint' => __('cashier_expense.hints.price_usd'),
            // Product purchases only; JS toggles this with the product field.
            'wrapper' => [
                'class' => 'form-group col-sm-12 mb-3 d-none',
            ],
        ]);

        CRUD::addField([
            'name' => 'amount_gel',
            'label' => __('cashier_expense.amount_gel_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0.01',
                'required' => true,
            ],
            'suffix' => '₾',
            'hint' => __('cashier_expense.hints.amount_gel'),
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'credit',
            'label' => __('cashier_expense.credit_field'),
            'type' => 'number',
            'default' => 0,
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'suffix' => '₾',
            'hint' => __('cashier_expense.hints.credit'),
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => __('cashier_expense.description'),
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'file',
            'label' => __('cashier_expense.file'),
            'type' => 'upload',
            'upload' => true,
            'disk' => 'public',
            'attributes' => [
                'accept' => '.pdf,.png,.jpeg,.jpg',
            ],
            'hint' => __('cashier_expense.hints.file'),
        ]);

        CRUD::addField([
            'name' => 'expense_date',
            'label' => __('cashier_expense.expense_date'),
            'type' => 'datetime_picker',
            'default' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();

        $entry = $this->crud->getCurrentEntry();

        // Keep the saved supplier selectable even if it was unlinked from the
        // category since; otherwise editing the row would silently drop it.
        if ($entry && $entry->category_id && $entry->supplier_id && $entry->supplier) {
            $supplierOptions = ExpenseCategory::supplierOptionsMap();
            $forCategory = $supplierOptions[$entry->category_id] ?? [];
            $alreadyListed = collect($forCategory)->contains('id', (int) $entry->supplier_id);

            if (! $alreadyListed) {
                $forCategory[] = ['id' => (int) $entry->supplier_id, 'name' => $entry->supplier->name];
                $supplierOptions[$entry->category_id] = $forCategory;

                Widget::add([
                    'name' => 'cashier_expense_supplier_config',
                    'type' => 'view',
                    'view' => 'vendor.backpack.crud.widgets.cashier_expense_supplier_config',
                    'supplierOptions' => $supplierOptions,
                    'productionCategoryIds' => ExpenseCategory::productionCategoryIds(),
                    'supplierPrices' => SupplierPrice::priceMap(),
                ]);
            }
        }

        // Keep the current category visible if it stopped being a leaf after
        // children were added under it; validation still requires a leaf.
        if ($entry && $entry->category_id && $entry->category) {
            $options = ExpenseCategory::groupedLeafOptions();
            $flat = [];
            foreach ($options as $items) {
                $flat += $items;
            }
            if (! array_key_exists($entry->category_id, $flat)) {
                $options[''][$entry->category_id] = __('cashier_expense.category_has_children', ['name' => $entry->category->name]);
                CRUD::modifyField('category_id', ['options' => $options]);
            }
        }
    }

    protected function setupShowOperation(): void
    {
        $this->autoSetupShowOperation();

        // The auto-generated columns come from the DB, so status would print the
        // raw 'draft'/'confirmed' value; show the translated label instead.
        CRUD::modifyColumn('status', [
            'label' => __('cashier_expense.status'),
            'type' => 'select_from_array',
            'options' => CashierExpense::statuses(),
        ]);
    }

    /**
     * Include expenses whose category is the selected node or any descendant.
     */
    protected function applyCategoryFilter($query, int $categoryId)
    {
        $category = ExpenseCategory::find($categoryId);
        if (! $category) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('category', function ($q) use ($category) {
            $q->where('lft', '>=', $category->lft)
                ->where('rgt', '<=', $category->rgt);
        });
    }

    /**
     * Apply the list filters to a query so the
     * summary widget totals match exactly what the filtered table shows.
     */
    protected function applyExpenseFilters($query)
    {
        if (request()->filled('type')) {
            $query->where('type', request()->get('type'));
        }

        if (request()->filled('category_id')) {
            $this->applyCategoryFilter($query, (int) request()->get('category_id'));
        }

        if (request()->filled('supplier_id')) {
            $query->where('supplier_id', request()->get('supplier_id'));
        }

        if (request()->filled('product_id')) {
            $query->where('product_id', request()->get('product_id'));
        }

        if (request()->filled('expense_date')) {
            $dates = json_decode(request()->get('expense_date'), true);
            if (is_array($dates)) {
                if (!empty($dates['from'])) {
                    $query->where('expense_date', '>=', \Carbon\Carbon::parse($dates['from'])->startOfDay());
                }
                if (!empty($dates['to'])) {
                    $query->where('expense_date', '<=', \Carbon\Carbon::parse($dates['to'])->endOfDay());
                }
            }
        }

        return $query;
    }

    /**
     * Compute the filter-aware summary totals.
     *
     * Confirmed expenses only: a draft counts nowhere until it is confirmed, so
     * these totals can differ from the sum of the rows currently listed (the
     * widget says so).
     */
    protected function calculateExpenseStats(): array
    {
        $query = $this->applyExpenseFilters(CashierExpense::query()->confirmed());

        return [
            'totalAmount' => (float) (clone $query)->sum('amount_gel'),
            'totalCredit' => (float) (clone $query)->sum('credit'),
            'totalCash' => (float) (clone $query)->where('type', CashierExpense::TYPE_CASH)->sum(\DB::raw('amount_gel - credit')),
            'totalTransfer' => (float) (clone $query)->where('type', CashierExpense::TYPE_TRANSFER)->sum(\DB::raw('amount_gel - credit')),
            'totalPmTransfer' => (float) (clone $query)->where('type', CashierExpense::TYPE_PM_TRANSFER)->sum(\DB::raw('amount_gel - credit')),
        ];
    }

    /**
     * Add a summary widget totaling expenses for the current list filters.
     */
    protected function addExpenseStatsWidget(): void
    {
        Widget::add(array_merge([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.cashier_expense_stats',
            'wrapper' => ['class' => 'col-12'],
        ], $this->calculateExpenseStats()))->to('before_content');
    }

    /**
     * Return the filter-aware summary totals as JSON (for live widget updates).
     */
    public function getExpenseStats(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->calculateExpenseStats());
    }
}

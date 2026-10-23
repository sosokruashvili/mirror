<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SafeTransferRequest;
use App\Models\SafeTransfer;
use App\Services\CashierService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

class SafeCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(SafeTransfer::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/safe');
        CRUD::setEntityNameStrings(__('safe.entity'), __('safe.entity_plural'));

        $this->crud->enableExportButtons();

        $this->crud->setAccessCondition('update', function ($entry) {
            return $entry === null || $entry->canBeEditedBy(backpack_user());
        });
        $this->crud->setAccessCondition('delete', function ($entry) {
            return $entry === null || $entry->canBeDeletedBy(backpack_user());
        });
    }

    protected function setupListOperation(): void
    {
        $this->crud->query->with('authorUser');

        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.safe_stats',
            'wrapper' => ['class' => 'col-12'],
            'stats' => app(CashierService::class)->getSafeStats(),
        ])->to('before_content');

        $this->addSafeColumns();
        $this->addSafeFilters();

        $this->crud->orderBy('transferred_at', 'desc');
    }

    protected function addSafeColumns(): void
    {
        CRUD::addColumn([
            'name' => 'transferred_at',
            'label' => __('safe.transferred_at'),
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'direction',
            'label' => __('safe.direction'),
            'type' => 'select_from_array',
            'options' => SafeTransfer::directions(),
        ]);

        CRUD::addColumn([
            'name' => 'amount_gel',
            'label' => __('safe.amount_gel'),
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => __('safe.status'),
            'type' => 'custom_html',
            'value' => function ($entry) {
                return status_badge($entry->status);
            },
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => __('safe.description'),
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'author',
            'label' => __('safe.author'),
            'type' => 'relationship',
            'entity' => 'authorUser',
            'attribute' => 'name',
        ]);
    }

    protected function addSafeFilters(): void
    {
        CRUD::addFilter([
            'name' => 'status',
            'type' => 'select2',
            'label' => __('safe.status'),
        ], function () {
            return SafeTransfer::statuses();
        }, function ($value) {
            $this->crud->addClause('where', 'status', $value);
        });

        CRUD::addFilter([
            'name' => 'direction',
            'type' => 'select2',
            'label' => __('safe.direction'),
        ], function () {
            return SafeTransfer::directions();
        }, function ($value) {
            $this->crud->addClause('where', 'direction', $value);
        });

        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'author',
            'label' => __('safe.author'),
        ], function () {
            return \App\Models\User::orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'author', $value);
        });

        CRUD::addFilter([
            'type' => 'date_range',
            'name' => 'transferred_at',
            'label' => __('safe.filters.date_range'),
        ], false, function ($value) {
            $dates = json_decode($value, true);
            if (! empty($dates['from'])) {
                $this->crud->addClause('where', 'transferred_at', '>=', \Carbon\Carbon::parse($dates['from'])->startOfDay());
            }
            if (! empty($dates['to'])) {
                $this->crud->addClause('where', 'transferred_at', '<=', \Carbon\Carbon::parse($dates['to'])->endOfDay());
            }
        });
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SafeTransferRequest::class);

        CRUD::addField([
            'name' => 'status',
            'label' => __('safe.status'),
            'type' => 'select_from_array',
            'options' => SafeTransfer::statuses(),
            'allows_null' => false,
            'default' => SafeTransfer::STATUS_DRAFT,
            'hint' => __('safe.hints.status'),
        ]);

        CRUD::addField([
            'name' => 'direction',
            'label' => __('safe.direction'),
            'type' => 'select_from_array',
            'options' => SafeTransfer::directions(),
            'allows_null' => false,
            'default' => SafeTransfer::DIRECTION_TO_SAFE,
            'hint' => __('safe.hints.direction'),
        ]);

        CRUD::addField([
            'name' => 'amount_gel',
            'label' => __('safe.amount_gel_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0.01',
                'required' => true,
            ],
            'suffix' => '₾',
        ]);

        CRUD::addField([
            'name' => 'transferred_at',
            'label' => __('safe.transferred_at'),
            'type' => 'datetime_picker',
            'default' => now()->format('Y-m-d H:i:s'),
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => __('safe.description'),
            'type' => 'textarea',
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $this->addSafeColumns();

        CRUD::modifyColumn('status', [
            'type' => 'select_from_array',
            'options' => SafeTransfer::statuses(),
        ]);
    }
}

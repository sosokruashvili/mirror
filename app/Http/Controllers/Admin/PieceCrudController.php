<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

/**
 * Class PieceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PieceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Piece::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/piece');
        CRUD::setEntityNameStrings('piece', 'pieces');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->crud->addClause('with', [
            'order.products',
            'brokenGlasses' => fn ($query) => $query->orderBy('id'),
        ]);

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        

        CRUD::addColumn([
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'relationship',
            'entity' => 'order',
            'attribute' => 'id', // Show the Order ID
            'model' => \App\Models\Order::class,
        ]);

        CRUD::addColumn([
            'name' => 'product_title',
            'label' => 'Product',
            'type' => 'text',
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('order.products', function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%');
                });
            },
            'value' => function ($entry) {
                if (!$entry->order) {
                    return '';
                }

                return $entry->order->products->pluck('title')->implode(' x ');
            },
        ]);

        CRUD::addColumn([
            'name' => 'order.product_type',
            'label' => 'Order Product Type',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'stage',
            'label' => 'Stage',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => function ($entry) {
                if (!$entry->stage) {
                    return '<span class="badge" style="background-color: ' . piece_draft_color() . '; color: #ffffff;">Draft</span>';
                }

                $bg = piece_stage_color($entry->stage);
                $text = piece_stage_text_color($entry->stage);
                $label = htmlspecialchars(piece_stage_ge($entry->stage), ENT_QUOTES, 'UTF-8');

                return '<span class="badge" style="background-color: ' . $bg . '; color: ' . $text . ';">' . $label . '</span>';
            }
        ]);

        CRUD::addColumn([
            'name' => 'broken_display',
            'label' => 'Broken',
            'type' => 'custom_html',
            'escaped' => false,
            'orderable' => false,
            'searchLogic' => false,
            'value' => function ($entry) {
                $records = $entry->brokenGlasses ?? collect();
                $recordCount = $records->count();
                $totalCount = max($recordCount, (int) ($entry->broken ?? 0));

                if ($totalCount === 0) {
                    return '';
                }

                $html = '<span class="piece-broken-icons">';

                foreach ($records as $record) {
                    $description = htmlspecialchars($record->description ?? '', ENT_QUOTES, 'UTF-8');
                    $html .= '<span role="button" tabindex="0" class="badge bg-danger me-1 piece-broken-x-btn" data-description="' . $description . '" title="View description"><i class="la la-times"></i></span>';
                }

                for ($i = 0; $i < $totalCount - $recordCount; $i++) {
                    $html .= '<span role="button" tabindex="0" class="badge bg-danger me-1 piece-broken-x-btn" data-description="" title="View description"><i class="la la-times"></i></span>';
                }

                $html .= '</span>';

                return $html;
            },
        ]);

        CRUD::addColumn([
            'name' => 'width',
            'label' => 'Width',
            'type' => 'number',
            'decimals' => 0,
            'thousands_sep' => '',
        ]);

        CRUD::addColumn([
            'name' => 'height',
            'label' => 'Height',
            'type' => 'number',
            'decimals' => 0,
            'thousands_sep' => '',
        ]);

        

        CRUD::addColumn([
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        // Add filter for order_id if present in URL
        if (request()->has('order_id')) {
            $this->crud->addClause('where', 'order_id', request()->get('order_id'));
        }

        // Add filter for order_id
        $this->crud->addFilter([
            'name' => 'order_id',
            'type' => 'select2',
            'label' => 'Order'
        ], function() {
            return \App\Models\Order::all()->pluck('id', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('where', 'order_id', $value);
        });

        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.piece_broken_modal',
        ])->to('after_content');

        Widget::add()->type('script')->content('assets/js/piece-broken-list.js');
    }


    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::addField([
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'number',
            'attributes' => [
                'min' => '1',
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'select',
            'entity' => 'order',
            'attribute' => 'name',
            'model' => \App\Models\Order::class,
        ]);

        CRUD::addField([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => \App\Models\Product::class,
        ]);

        CRUD::addField([
            'name' => 'width',
            'label' => 'Width',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'height',
            'label' => 'Height',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'stage',
            'label' => 'Stage (ეტაპი)',
            'type' => 'select_from_array',
            'options' => piece_stages(),
            'allows_null' => true,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\StageRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class StageCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class StageCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Stage::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/stage');
        CRUD::setEntityNameStrings('stage', 'stages');
    }

    protected function setupListOperation()
    {
        // Show stages in their production order.
        $this->crud->orderBy('position')->orderBy('id');

        CRUD::addColumn([
            'name' => 'position',
            'label' => 'Order',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'color',
            'label' => 'Color',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => function ($entry) {
                $color = htmlspecialchars((string) $entry->color, ENT_QUOTES, 'UTF-8');
                $text = htmlspecialchars($entry->text_color, ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars((string) $entry->title, ENT_QUOTES, 'UTF-8');

                return '<span class="badge" style="background-color: ' . $color . '; color: ' . $text . ';">' . $label . '</span>'
                    . ' <span class="text-muted">' . $color . '</span>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'is_universal',
            'label' => 'Universal',
            'type' => 'boolean',
            'options' => [0 => 'No', 1 => 'Yes'],
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(StageRequest::class);

        CRUD::addField([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'hint' => 'Display label shown to users (e.g. მოჭრა).',
            'attributes' => ['required' => true],
        ]);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'hint' => 'Machine identifier stored on pieces (e.g. cutting). Changing this on an existing stage will unlink pieces already set to the old value.',
            'attributes' => ['required' => true],
        ]);

        CRUD::addField([
            'name' => 'color',
            'label' => 'Color',
            'type' => 'color',
            'default' => '#64748B',
            'hint' => 'Badge color for this stage.',
        ]);

        CRUD::addField([
            'name' => 'position',
            'label' => 'Order',
            'type' => 'number',
            'hint' => 'Lower numbers appear first everywhere stages are listed.',
            'default' => 0,
        ]);

        CRUD::addField([
            'name' => 'is_universal',
            'label' => 'Universal stage',
            'type' => 'checkbox',
            'hint' => 'When on, this stage applies to every piece regardless of its services (e.g. მოჭრა, დასრულება).',
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
    }
}

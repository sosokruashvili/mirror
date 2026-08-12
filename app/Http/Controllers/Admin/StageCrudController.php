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
        CRUD::setEntityNameStrings(__('stage.entity'), __('stage.entity_plural'));
    }

    protected function setupListOperation()
    {
        // Show stages in their production order.
        $this->crud->orderBy('position')->orderBy('id');

        CRUD::addColumn([
            'name' => 'position',
            'label' => __('stage.position'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'title',
            'label' => __('stage.title'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => __('stage.name'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'color',
            'label' => __('stage.color'),
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
            'label' => __('stage.is_universal'),
            'type' => 'boolean',
            'options' => __('stage.yes_no'),
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(StageRequest::class);

        CRUD::addField([
            'name' => 'title',
            'label' => __('stage.title'),
            'type' => 'text',
            'hint' => __('stage.hints.title'),
            'attributes' => ['required' => true],
        ]);

        CRUD::addField([
            'name' => 'name',
            'label' => __('stage.name'),
            'type' => 'text',
            'hint' => __('stage.hints.name'),
            'attributes' => [
                'required' => true,
                'placeholder' => 'frame_assembly',
                'pattern' => '[A-Za-z0-9_-]+',
                'title' => __('stage.name_pattern_title'),
            ],
        ]);

        CRUD::addField([
            'name' => 'color',
            'label' => __('stage.color'),
            'type' => 'color',
            'default' => '#64748B',
            'hint' => __('stage.hints.color'),
        ]);

        CRUD::addField([
            'name' => 'position',
            'label' => __('stage.position'),
            'type' => 'number',
            'hint' => __('stage.hints.position'),
            'default' => 0,
        ]);

        CRUD::addField([
            'name' => 'is_universal',
            'label' => __('stage.is_universal_field'),
            'type' => 'checkbox',
            'hint' => __('stage.hints.is_universal'),
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

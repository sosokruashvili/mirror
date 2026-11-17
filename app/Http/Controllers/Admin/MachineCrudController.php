<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MachineRequest;
use App\Models\Machine;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Machines offered in the team's "გატყდა" dialog (where a glass broke).
 *
 * No delete: broken-glass records keep pointing at their machine, so a machine
 * that is no longer used is switched off ("აქტიური" unticked) and drops out of
 * the dialog while old records keep its name.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MachineCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Machine::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/machine');
        CRUD::setEntityNameStrings(__('machine.entity'), __('machine.entity_plural'));
    }

    protected function setupListOperation()
    {
        $this->crud->orderBy('position')->orderBy('id');
        $this->crud->query->withCount('brokenGlasses');

        CRUD::addColumn(['name' => 'position', 'label' => __('machine.position'), 'type' => 'number']);
        CRUD::addColumn(['name' => 'name', 'label' => __('machine.name'), 'type' => 'text']);
        CRUD::addColumn([
            'name' => 'requires_comment',
            'label' => __('machine.requires_comment'),
            'type' => 'boolean',
            'options' => [0 => __('machine.no'), 1 => __('machine.yes')],
        ]);
        CRUD::addColumn([
            'name' => 'is_active',
            'label' => __('machine.is_active'),
            'type' => 'boolean',
            'options' => [0 => __('machine.no'), 1 => __('machine.yes')],
        ]);
        CRUD::addColumn([
            'name' => 'broken_glasses_count',
            'label' => __('machine.breaks'),
            'type' => 'number',
            'searchLogic' => false,
            'orderable' => false,
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(MachineRequest::class);

        CRUD::addField(['name' => 'name', 'label' => __('machine.name'), 'type' => 'text']);
        CRUD::addField([
            'name' => 'position',
            'label' => __('machine.position'),
            'type' => 'number',
            'default' => (int) Machine::query()->max('position') + 1,
            'hint' => __('machine.position_hint'),
        ]);
        CRUD::addField([
            'name' => 'requires_comment',
            'label' => __('machine.requires_comment'),
            'type' => 'checkbox',
            'hint' => __('machine.requires_comment_hint'),
        ]);
        CRUD::addField([
            'name' => 'is_active',
            'label' => __('machine.is_active'),
            'type' => 'checkbox',
            'default' => true,
            'hint' => __('machine.is_active_hint'),
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RoleRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class RoleCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class RoleCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
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
        CRUD::setModel(\App\Models\Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/role');
        CRUD::setEntityNameStrings('role', 'roles');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'permissions',
            'label' => 'Permissions',
            'type' => 'select_multiple',
            'entity' => 'permissions',
            'attribute' => 'label',
            'model' => \App\Models\Permission::class,
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(RoleRequest::class);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'hint' => 'Unique identifier used in code, e.g. "team"',
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'permissions',
            'label' => 'Permissions',
            'type' => 'checklist',
            'entity' => 'permissions',
            'attribute' => 'label',
            'model' => \App\Models\Permission::class,
            'pivot' => true,
            'number_of_columns' => 2,
            'hint' => 'Page permissions (grouped by page) are listed first, followed by production-stage capabilities. Administrators and Developers always have full access regardless of these boxes.',
            // Group page permissions together (by page), stage permissions last.
            // Return an id => label array (label is an accessor, not a column),
            // preserving the ordering.
            'options' => (function ($query) {
                return $query->orderBy('type')->orderBy('name')->get()->pluck('label', 'id')->toArray();
            }),
        ]);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        // The super roles are the anchor of the whole access system (see the
        // Gate::before bypass, which matches them by slug). Never let their
        // slug be changed, or their holders would silently lose the bypass.
        if ($this->isSuperRole($this->crud->getCurrentEntryId())) {
            CRUD::modifyField('slug', ['attributes' => ['readonly' => 'readonly']]);
        }
    }

    /**
     * Prevent deletion of the Administrator and Developer roles.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        if ($this->isSuperRole($id)) {
            abort(403, 'This role grants unrestricted access and cannot be deleted.');
        }

        return $this->crud->delete($id);
    }

    private function isSuperRole($id): bool
    {
        return $id && \App\Models\Role::whereKey($id)
            ->whereIn('slug', config('access.super_roles', ['admin']))
            ->exists();
    }
}

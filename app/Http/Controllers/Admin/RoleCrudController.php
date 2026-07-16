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
            'hint' => 'Page permissions (grouped by page) are listed first, followed by production-stage capabilities. Administrators always have full access regardless of these boxes.',
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

        // The Administrator role is the anchor of the whole access system
        // (see the Gate::before bypass, which matches its "admin" slug). Never
        // let its slug be changed, or admins would silently lose their bypass.
        if ($this->isAdminRole($this->crud->getCurrentEntryId())) {
            CRUD::modifyField('slug', ['attributes' => ['readonly' => 'readonly']]);
        }
    }

    /**
     * Prevent deletion of the Administrator role.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        if ($this->isAdminRole($id)) {
            abort(403, 'The Administrator role cannot be deleted.');
        }

        return $this->crud->delete($id);
    }

    private function isAdminRole($id): bool
    {
        return $id && \App\Models\Role::whereKey($id)->where('slug', 'admin')->exists();
    }
}

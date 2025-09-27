<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class UserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
{
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
        CRUD::setModel(\App\Models\User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user');
        CRUD::setEntityNameStrings('user', 'users');
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
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
        ]);

        CRUD::addColumn([
            'name' => 'phone',
            'label' => 'Phone',
            'type' => 'phone',
        ]);

        CRUD::addColumn([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => \App\Models\Role::class,
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created',
            'type' => 'datetime',
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
        CRUD::setValidation(UserRequest::class);
        
        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'phone',
            'label' => 'Phone',
            'type' => 'phone',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ],
            'config' => [
                'onlyCountries' => ['ge'],
                'initialCountry' => 'ge',
                'separateDialCode' => true,
                'nationalMode' => true,
                'autoHideDialCode' => false,
                'placeholderNumberType' => 'MOBILE',
            ]
        ]);

        CRUD::addField([
            'name' => 'password',
            'label' => 'Password',
            'type' => 'password',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => \App\Models\Role::class,
            'pivot' => true,
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
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
        CRUD::setValidation(UserRequest::class);
        
        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'phone',
            'label' => 'Phone',
            'type' => 'phone',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ],
            'config' => [
                'onlyCountries' => ['ge'],
                'initialCountry' => 'ge',
                'separateDialCode' => true,
                'nationalMode' => true,
                'autoHideDialCode' => false,
                'placeholderNumberType' => 'MOBILE',
            ]
        ]);

        CRUD::addField([
            'name' => 'password',
            'label' => 'Password',
            'type' => 'password',
            'hint' => 'Leave empty to keep current password',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'roles',
            'label' => 'Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'model' => \App\Models\Role::class,
            'pivot' => true,
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        $item = $this->crud->create($this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $item;

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();
        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();
        
        // Remove password from request if it's empty
        $data = $this->crud->getStrippedSaveRequest($request);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        
        $item = $this->crud->update($request->get($this->crud->model->getKeyName()), $data);
        $this->data['entry'] = $this->crud->entry = $item;

        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();
        return $this->crud->performSaveAction($item->getKey());
    }
}

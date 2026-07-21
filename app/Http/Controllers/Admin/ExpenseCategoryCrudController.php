<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ExpenseCategoryCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation {
        destroy as traitDestroy;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;

    public function setup(): void
    {
        CRUD::setModel(ExpenseCategory::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/expense-category');
        CRUD::setEntityNameStrings('expense category', 'expense categories');
    }

    protected function setupListOperation(): void
    {
        $this->crud->orderBy('lft')->orderBy('id');

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'closure',
            'function' => fn (ExpenseCategory $entry) => $entry->indentedName(),
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('name', 'like', '%' . $searchTerm . '%');
            },
        ]);

        CRUD::addColumn([
            'name' => 'parent_id',
            'label' => 'Parent',
            'type' => 'select',
            'entity' => 'parent',
            'attribute' => 'name',
            'model' => ExpenseCategory::class,
        ]);

        CRUD::addColumn([
            'name' => 'depth',
            'label' => 'Depth',
            'type' => 'number',
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ExpenseCategoryRequest::class);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'parent_id',
            'label' => 'Parent',
            'type' => 'select_from_array',
            'options' => ExpenseCategory::optionsForSelect(),
            'allows_null' => true,
            'hint' => 'Leave empty for a top-level category. You can also nest via Reorder.',
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(ExpenseCategoryRequest::class);

        $id = (int) ($this->crud->getCurrentEntryId() ?? 0);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'parent_id',
            'label' => 'Parent',
            'type' => 'select_from_array',
            'options' => ExpenseCategory::optionsForSelect($id ?: null),
            'allows_null' => true,
            'hint' => 'Leave empty for a top-level category. You can also nest via Reorder.',
        ]);
    }

    protected function setupReorderOperation(): void
    {
        CRUD::set('reorder.label', 'name');
        CRUD::set('reorder.max_level', 10);
    }

    /**
     * Block delete when the category has children or linked expenses.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $category = ExpenseCategory::findOrFail($id);

        if ($category->children()->exists()) {
            return response()->json([
                'error' => ['message' => 'Cannot delete a category that has child categories. Move or delete children first.'],
            ], 403);
        }

        if ($category->expenses()->exists()) {
            return response()->json([
                'error' => ['message' => 'Cannot delete a category that is used by expenses.'],
            ], 403);
        }

        return $this->traitDestroy($id);
    }
}

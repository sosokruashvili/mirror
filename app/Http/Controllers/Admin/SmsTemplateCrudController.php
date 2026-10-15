<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SmsTemplateRequest;
use App\Models\SmsTemplate;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Reusable message texts. Notifications store the template they were
 * created from; placeholders in the text are filled at that moment.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SmsTemplateCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as traitDestroy; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(SmsTemplate::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/sms-template');
        CRUD::setEntityNameStrings(__('sms_template.entity'), __('sms_template.entity_plural'));

        // A keyed template is how the system knows which text to send.
        CRUD::setAccessCondition('delete', function ($entry) {
            if (! $entry) {
                return true;
            }

            return blank($entry->key);
        });
    }

    protected function setupListOperation()
    {
        $this->crud->orderBy('title');

        CRUD::addColumn([
            'name' => 'title',
            'label' => __('sms_template.title'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'key',
            'label' => __('sms_template.key'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'text',
            'label' => __('sms_template.text'),
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'updated_at',
            'label' => __('sms_template.updated_at'),
            'type' => 'datetime',
        ]);
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(SmsTemplateRequest::class);

        CRUD::addField([
            'name' => 'title',
            'label' => __('sms_template.title'),
            'type' => 'text',
            'attributes' => ['required' => true, 'maxlength' => 255],
        ]);

        CRUD::addField([
            'name' => 'text',
            'label' => __('sms_template.text'),
            'type' => 'textarea',
            'hint' => __('sms_template.hints.text', ['variables' => SmsTemplate::variableHint()]),
            'attributes' => [
                'required' => true,
                'rows' => 5,
                'maxlength' => 1000,
            ],
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        $entry = $this->crud->getCurrentEntry();

        if ($entry instanceof SmsTemplate && filled($entry->key)) {
            CRUD::addField([
                'name' => 'system_key_note',
                'type' => 'custom_html',
                'value' => '<p class="help-block">'.e(__('sms_template.system_key', ['key' => $entry->key])).'</p>',
            ]);
        }
    }

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $template = SmsTemplate::query()->findOrFail($id);

        if (filled($template->key)) {
            return response()->json([
                'error' => [__('sms_template.errors.system')],
            ], 403);
        }

        return $this->traitDestroy($id);
    }
}

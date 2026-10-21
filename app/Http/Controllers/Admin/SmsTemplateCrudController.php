<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Sms;
use App\Http\Requests\SmsTemplateRequest;
use App\Models\SmsTemplate;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsMessage;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

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

            return ! $entry->isSystem();
        });
    }

    protected function setupListOperation()
    {
        $this->crud->orderBy('title');
        $this->crud->addButton('line', 'sms_template_test', 'view', 'crud::buttons.sms_template_test', 'beginning');

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
            'name' => 'key',
            'label' => __('sms_template.key'),
            'type' => 'text',
            'hint' => __('sms_template.hints.key'),
            'attributes' => [
                'maxlength' => 64,
                'placeholder' => 'order_ready',
            ],
        ]);

        CRUD::addField([
            'name' => 'text',
            'label' => __('sms_template.text'),
            'type' => 'sms_textarea',
            'samples' => SmsTemplate::counterSamples(),
            // Their length depends on the client.
            'approx' => ['client_name', 'client_phone'],
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

        if ($entry instanceof SmsTemplate && $entry->isSystem()) {
            CRUD::removeField('key');
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

        if ($template->isSystem()) {
            return response()->json([
                'error' => [__('sms_template.errors.system')],
            ], 403);
        }

        return $this->traitDestroy($id);
    }

    /**
     * Send this template now, to the number typed in the test modal.
     * This does not queue a notification and does not message a client.
     */
    public function sendTest(Request $request, int $id)
    {
        $this->crud->hasAccessOrFail('update');

        $template = SmsTemplate::query()->findOrFail($id);
        $phone = trim((string) $request->input('phone'));

        if (PhoneNumber::normalize($phone) === null) {
            return response()->json(['message' => __('notification.errors.phone')], 422);
        }

        $body = trim($template->renderForTest($phone));

        if ($body === '') {
            return response()->json(['message' => __('notification.errors.body')], 422);
        }

        if (mb_strlen($body) > 1000) {
            return response()->json(['message' => __('notification.errors.too_long')], 422);
        }

        try {
            Sms::send($phone, SmsMessage::create($body)->reference(substr('tpl'.$template->id, 0, 20)));
        } catch (SmsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('sms_template.test.sent'),
        ]);
    }
}

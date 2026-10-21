<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\NotificationRequest;
use App\Models\Client;
use App\Models\Notification;
use App\Models\Order;
use App\Models\SmsTemplate;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Str;

/**
 * Outbound notifications. Saving an SMS only queues it; `notifications:send`
 * delivers those every two minutes. A cabinet notification is shown in that
 * client's cabinet and is not sent.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class NotificationCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; edit as traitEdit; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(Notification::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/notification');
        CRUD::setEntityNameStrings(__('notification.entity'), __('notification.entity_plural'));

        $this->crud->enableExportButtons();

        // Sent and in-flight rows stay as they are. A null entry means the
        // check ran before the row was loaded (the edit/update actions), and
        // those actions repeat the check once they know the id.
        CRUD::setAccessCondition('update', function ($entry) {
            if (! $entry) {
                return true;
            }

            return $entry->isEditable();
        });
    }

    protected function setupListOperation()
    {
        $this->crud->orderBy('id', 'desc');
        $this->crud->with([
            // Order rows show their client's name next to the order link.
            'notifiable' => fn ($morphTo) => $morphTo->morphWith([Order::class => ['client']]),
            'authorUser',
            'smsTemplate',
        ]);

        $this->setupColumns();
        $this->setupFilters();
    }

    protected function setupShowOperation()
    {
        $this->crud->with(['notifiable', 'authorUser', 'smsTemplate']);
        $this->setupColumns();

        CRUD::addColumn([
            'name' => 'gateway_reference',
            'label' => __('notification.gateway_reference'),
            'type' => 'text',
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->addNotificationFields();
    }

    protected function setupUpdateOperation()
    {
        $entry = $this->crud->getCurrentEntry();

        $this->addNotificationFields($entry instanceof Notification ? $entry : null);
    }

    protected function addNotificationFields(?Notification $entry = null): void
    {
        CRUD::setValidation(NotificationRequest::class);

        $cabinet = $entry?->type === Notification::TYPE_CABINET;
        $clientId = null;

        if ($entry && $entry->notifiable_type === (new Client())->getMorphClass()) {
            $clientId = $entry->notifiable_id;
        }

        $clients = Client::query()
            ->orderBy('name')
            ->get(['id', 'name', 'legal_id', 'personal_id', 'phone_number']);

        CRUD::addField([
            'name' => 'type',
            'label' => __('notification.type'),
            'type' => 'select_from_array',
            'options' => Notification::typeOptions(),
            'allows_null' => false,
            'default' => Notification::TYPE_SMS,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'sms_template_id',
            'label' => __('notification.template'),
            'type' => 'select2',
            'entity' => 'smsTemplate',
            'model' => SmsTemplate::class,
            'attribute' => 'title',
            'allows_null' => true,
            'placeholder' => __('notification.select_template'),
            'hint' => __('notification.hints.template'),
            'options' => function ($query) {
                return $query->orderBy('title')->get();
            },
            'wrapper' => ['class' => 'form-group col-md-6 notification-template-field'],
        ]);

        $recipientWrapper = ['class' => 'form-group col-md-6 notification-recipient-field'];

        if ($cabinet) {
            $recipientWrapper['style'] = 'display:none';
        }

        CRUD::addField([
            'name' => 'recipient',
            'label' => __('notification.recipient'),
            'type' => 'text',
            'hint' => __('notification.hints.recipient'),
            'wrapper' => $recipientWrapper,
        ]);

        CRUD::addField([
            'name' => 'client_id',
            'label' => __('notification.client'),
            'type' => 'select2_from_array',
            'options' => $clients->mapWithKeys(fn (Client $client) => [$client->id => $client->name_with_id])->all(),
            'allows_null' => true,
            'value' => $clientId,
            'placeholder' => __('notification.select_client'),
            'hint' => __('notification.hints.client'),
            'wrapper' => ['class' => 'form-group col-md-6 notification-client-field'],
        ]);

        CRUD::addField([
            'name' => 'body',
            'label' => __('notification.body'),
            'type' => 'textarea',
            'hint' => $cabinet ? __('notification.hints.cabinet') : __('notification.hints.queued'),
            'attributes' => [
                'rows' => 4,
                'maxlength' => 1000,
            ],
            'wrapper' => ['class' => 'form-group col-sm-12 mb-3 notification-body-field'],
        ]);

        CRUD::addField([
            'name' => 'type_toggle',
            'type' => 'custom_html',
            'value' => view('admin.notifications.type-fields-script', [
                'smsHint' => __('notification.hints.queued'),
                'cabinetHint' => __('notification.hints.cabinet'),
                'cabinetType' => Notification::TYPE_CABINET,
                'templates' => (object) SmsTemplate::query()->orderBy('title')->pluck('text', 'id')->all(),
                'clients' => (object) $clients->mapWithKeys(fn (Client $client) => [$client->id => [
                    'name' => $client->name,
                    'phone' => (string) $client->phone_number,
                ]])->all(),
            ])->render(),
        ]);
    }

    public function edit($id)
    {
        $this->assertEditable($this->crud->getCurrentEntryId() ?: $id);

        return $this->traitEdit($id);
    }

    public function store()
    {
        $response = $this->traitStore();

        if ($this->crud->entry instanceof Notification) {
            $this->attachClient($this->crud->entry);
        }

        return $response;
    }

    public function update()
    {
        $id = $this->crud->getCurrentEntryId();
        $this->assertEditable($id);

        $before = Notification::query()->find($id);
        $wasCabinet = $before?->type === Notification::TYPE_CABINET;

        $response = $this->traitUpdate();

        $notification = Notification::query()->find($id);

        if ($notification) {
            $this->attachClient($notification);
        }

        if (
            $notification
            && $notification->type === Notification::TYPE_SMS
            && $notification->status !== Notification::STATUS_SENDING
            && ($notification->isEditable() || $wasCabinet)
        ) {
            // A failed or pending SMS is edited to be tried again. A cabinet
            // row switched to SMS has to join that queue too. Leave a row
            // alone when the sender already claimed or delivered it.
            $notification->releaseForSending();
        }

        return $response;
    }

    /**
     * The client select is not a column. Cabinet rows also take their
     * recipient from that client; an SMS keeps the phone that was saved.
     */
    protected function attachClient(Notification $notification): void
    {
        $client = Client::query()->find((int) request()->input('client_id'));

        if (! $client) {
            return;
        }

        $attributes = [
            'notifiable_type' => $client->getMorphClass(),
            'notifiable_id' => $client->getKey(),
        ];

        if ($notification->type === Notification::TYPE_CABINET) {
            $attributes['recipient'] = Notification::cabinetRecipient($client);
            $attributes['status'] = Notification::STATUS_SENT;
            $attributes['sent_at'] = $notification->sent_at ?? now();
            $attributes['attempts'] = 0;
            $attributes['last_error'] = null;
        }

        $notification->forceFill($attributes)->save();
    }

    protected function assertEditable(mixed $id): void
    {
        if ($id === false || $id === null || $id === '') {
            abort(404);
        }

        $notification = Notification::query()->findOrFail($id);

        if (! $notification->isEditable()) {
            abort(403);
        }
    }

    protected function setupColumns(): void
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => __('notification.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => __('notification.created_at'),
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'type',
            'label' => __('notification.type'),
            'type' => 'select_from_array',
            'options' => Notification::typeOptions(),
        ]);

        CRUD::addColumn([
            'name' => 'smsTemplate',
            'label' => __('notification.template'),
            'type' => 'relationship',
            'attribute' => 'title',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('smsTemplate', function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => __('notification.status'),
            'type' => 'custom_html',
            'escaped' => false,
            'value' => function ($entry) {
                if ($entry->type === Notification::TYPE_CABINET) {
                    return '<span class="badge bg-primary">'.e(__('notification.in_cabinet')).'</span>';
                }

                $class = match ($entry->status) {
                    Notification::STATUS_SENT => 'badge bg-success',
                    Notification::STATUS_FAILED => 'badge bg-danger',
                    Notification::STATUS_SENDING => 'badge bg-info',
                    default => 'badge bg-warning',
                };

                return '<span class="'.$class.'">'.e(__('notification.statuses.'.$entry->status)).'</span>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'recipient',
            'label' => __('notification.recipient'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'subject',
            'label' => __('notification.subject'),
            'type' => 'custom_html',
            'escaped' => false,
            'orderable' => false,
            'limit' => 1000,
            'value' => fn ($entry) => $this->subjectLink($entry),
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHasMorph('notifiable', [Client::class], function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        CRUD::addColumn([
            'name' => 'body',
            'label' => __('notification.body'),
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'attempts',
            'label' => __('notification.attempts'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'sent_at',
            'label' => __('notification.sent_at'),
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'last_error',
            'label' => __('notification.last_error'),
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'author',
            'label' => __('notification.author'),
            'type' => 'relationship',
            'entity' => 'authorUser',
            'attribute' => 'name',
        ]);
    }

    protected function setupFilters(): void
    {
        CRUD::addFilter([
            'name' => 'type',
            'type' => 'dropdown',
            'label' => __('notification.type'),
        ], Notification::typeOptions(), function ($value) {
            $this->crud->addClause('where', 'type', $value);
        });

        CRUD::addFilter([
            'name' => 'sms_template_id',
            'type' => 'dropdown',
            'label' => __('notification.template'),
        ], function () {
            return SmsTemplate::query()->orderBy('title')->pluck('title', 'id')->all();
        }, function ($value) {
            $this->crud->addClause('where', 'sms_template_id', $value);
        });

        CRUD::addFilter([
            'name' => 'status',
            'type' => 'dropdown',
            'label' => __('notification.status'),
        ], __('notification.statuses'), function ($value) {
            $this->crud->addClause('where', 'status', $value);
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'recipient',
            'label' => __('notification.recipient'),
        ], false, function ($value) {
            $this->crud->addClause('where', 'recipient', 'like', '%'.$value.'%');
        });
    }

    protected function subjectLink(Notification $entry): string
    {
        $subject = $entry->notifiable;

        if (! $subject) {
            return '—';
        }

        $label = e($subject->name ?? $subject->title ?? ('#'.$subject->getKey()));

        if ($subject instanceof Client) {
            return '<a href="'.e(backpack_url('client/'.$subject->getKey().'/show')).'">'.$label.'</a>';
        }

        if ($subject instanceof Order) {
            $link = '<a href="'.e(backpack_url('order/'.$subject->getKey().'/show')).'">'
                .e(Str::ucfirst(__('order.entity')).' #'.$subject->getKey()).'</a>';

            return $subject->client ? $link.' · '.e($subject->client->name) : $link;
        }

        return e(class_basename($subject)).': '.$label;
    }
}

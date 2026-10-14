<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClientAccount;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\Rule;

/**
 * Class ClientCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ClientCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Client::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/client');
        CRUD::setEntityNameStrings(__('client.entity'), __('client.entity_plural'));
        
        // Enable export buttons
        $this->crud->enableExportButtons();
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
            'name' => 'id',
            'label' => __('client.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => __('client.name'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'author',
            'label' => __('client.author'),
            'type' => 'relationship',
            'entity' => 'authorUser',
            'attribute' => 'name',
        ]);

        CRUD::addColumn([
            'name' => 'email',
            'label' => __('client.email'),
            'type' => 'email',
        ]);

        CRUD::addColumn([
            'name' => 'phone_number',
            'label' => __('client.phone'),
            'type' => 'phone',
        ]);

        
        // Add client type column
        CRUD::addColumn([
            'name' => 'client_type',
            'label' => __('client.type'),
            'type' => 'boolean',
            'options' => __('client.types'),
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    return $entry->client_type ? 'badge badge-success' : 'badge badge-info';
                }
            ]
        ]);
        
        CRUD::setFromDb();

        // setFromDb() derives labels from the column names ("Personal", "Starting
        // balance", ...), which bypasses the lang files - relabel those here.
        CRUD::modifyColumn('address', ['label' => __('client.address')]);
        CRUD::modifyColumn('personal_id', ['label' => __('client.personal_id')]);
        CRUD::modifyColumn('legal_id', ['label' => __('client.legal_id')]);
        CRUD::modifyColumn('starting_balance', ['label' => __('client.starting_balance')]);

        // Add Filters
        CRUD::addFilter([
            'name' => 'client_type',
            'type' => 'select2',
            'label' => __('client.client_type')
        ], function() {
            return __('client.types');
        }, function($value) {
            $this->crud->addClause('where', 'client_type', $value);
        });

        // Author filter (clients.author stores the creating user's id)
        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'author',
            'label' => __('client.author'),
        ], function () {
            return \App\Models\User::orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'author', $value);
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'name',
            'label' => __('client.name')
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'name', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'email',
            'label' => __('client.email')
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'email', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'phone_number',
            'label' => __('client.phone')
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'phone_number', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'address',
            'label' => __('client.address')
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'address', 'LIKE', "%{$value}%");
        });

        $this->crud->query->with(['cabinetAccount', 'authorUser']);

        CRUD::addColumn([
            'name' => 'cabinet_access',
            'label' => __('client.cabinet.column'),
            'type' => 'closure',
            'escaped' => false,
            'function' => function ($entry) {
                $on = (bool) $entry->cabinetAccount?->is_active;
                $class = $on ? 'badge bg-success' : 'badge bg-secondary';
                $label = e($on ? __('client.cabinet.on') : __('client.cabinet.off'));

                return '<span class="'.$class.'">'.$label.'</span>';
            },
        ]);
    }

    /**
     * setFromDb() only picks up $fillable columns, and author is intentionally
     * not fillable, so the preview has to add it itself.
     */
    protected function setupShowOperation()
    {
        $this->autoSetupShowOperation();

        CRUD::addColumn([
            'name' => 'author',
            'label' => __('client.author'),
            'type' => 'select',
            'entity' => 'authorUser',
            'attribute' => 'name',
            'model' => \App\Models\User::class,
        ]);
        CRUD::afterColumn('name');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        // Add client type select at the top
        CRUD::addField([
            'name' => 'client_type',
            'label' => __('client.client_type'),
            'type' => 'select_from_array',
            'options' => __('client.types'),
            'default' => 0,
            'hint' => __('client.hints.client_type'),
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'name',
            'label' => __('client.name'),
            'type' => 'text',
            'validationRules' => 'required',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'personal_id',
            'label' => __('client.personal_id'),
            'type' => 'text',
            'validationRules' => 'required_if:client_type,0|nullable|string|max:255|unique:clients,personal_id',
            'wrapper' => [
                'class' => 'form-group col-md-6 personal-id-field',
                'style' => 'display: none;'
            ],
            'attributes' => [
                'placeholder' => __('client.placeholders.personal_id'),
            ]
        ]);

        CRUD::addField([
            'name' => 'address',
            'label' => __('client.address'),
            'type' => 'textarea',
            'validationRules' => 'required',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        
        CRUD::addField([
            'name' => 'legal_id',
            'label' => __('client.legal_id'),
            'type' => 'text',
            'validationRules' => 'required_if:client_type,1',
            'wrapper' => [
                'class' => 'form-group col-md-6 legal-id-field',
                'style' => 'display: none;'
            ],
            'attributes' => [
                'placeholder' => __('client.placeholders.legal_id')
            ]
        ]);


        CRUD::addField([
            'name' => 'email',
            'label' => __('client.email'),
            'type' => 'email',
            'validationRules' => 'nullable|email|unique:clients,email',
            'wrapper' => [
                'class' => 'form-group col-md-6 email-field'
            ]
        ]);


        CRUD::addField([   // phone
            'name'  => 'phone_number', // db column for phone
            'label' => __('client.phone'),
            'type'  => 'phone',
            'validationRules' => 'required|unique:clients,phone_number',
            'wrapper' => [
                'class' => 'form-group col-md-6 phone-number-field'
            ],
            'attributes' => [
                'required' => true,
            ],
        
            // OPTIONALS
            // most options provided by intlTelInput.js are supported, you can try them out using the `config` attribute;
            //  take note that options defined in `config` will override any default values from the field;
            'config' => [
                'onlyCountries' => ['ge'],
                'initialCountry' => 'ge', // this needs to be in the allowed country list, either in `onlyCountries` or NOT in `excludeCountries`
                'separateDialCode' => true,
                'nationalMode' => true,
                'autoHideDialCode' => false,
                'placeholderNumberType' => 'MOBILE',
            ]
        ]);

        CRUD::addField([
            'name' => 'starting_balance',
            'label' => __('client.starting_balance'),
            'type' => 'number',
            'default' => 0,
            'attributes' => [
                'step' => '0.01',
            ],
            'hint' => __('client.hints.starting_balance'),
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);


        $this->addCabinetFields();

        // Labels the toggle below writes back into the DOM. Pre-encoded here so
        // the inline script stays readable and the translations are escaped once.
        $required = ' <span class="text-danger">*</span>';
        $personalIdLabel = json_encode(e(__('client.personal_id')));
        $legalIdLabel = json_encode(e(__('client.legal_id')));
        $personalIdLabelRequired = json_encode(e(__('client.personal_id')) . $required);
        $legalIdLabelRequired = json_encode(e(__('client.legal_id')) . $required);

        // Add JavaScript to handle the select functionality
        CRUD::addField([
            'name' => 'client_type_script',
            'type' => 'custom_html',
            'value' => '
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const clientTypeSelect = document.querySelector(\'select[name="client_type"]\');
                    const personalIdField = document.querySelector(\'.personal-id-field\');
                    const legalIdField = document.querySelector(\'.legal-id-field\');
                    const personalIdInput = document.querySelector(\'input[name="personal_id"]\');
                    const legalIdInput = document.querySelector(\'input[name="legal_id"]\');
                    
                    function toggleIdFields() {
                        if (clientTypeSelect.value == "1") {
                            // Legal client - show legal ID, hide personal ID
                            personalIdField.style.display = "none";
                            legalIdField.style.display = "block";
                            personalIdInput.value = "";
                            personalIdInput.removeAttribute("required");
                            legalIdInput.setAttribute("required", "required");
                            
                            // Update labels with asterisk
                            const personalLabel = personalIdField.querySelector("label");
                            const legalLabel = legalIdField.querySelector("label");
                            if (personalLabel) personalLabel.innerHTML = ' . $personalIdLabel . ';
                            if (legalLabel) legalLabel.innerHTML = ' . $legalIdLabelRequired . ';
                        } else {
                            // Individual client - show personal ID, hide legal ID
                            personalIdField.style.display = "block";
                            legalIdField.style.display = "none";
                            legalIdInput.value = "";
                            legalIdInput.removeAttribute("required");
                            personalIdInput.setAttribute("required", "required");
                            
                            // Update labels with asterisk
                            const personalLabel = personalIdField.querySelector("label");
                            const legalLabel = legalIdField.querySelector("label");
                            if (personalLabel) personalLabel.innerHTML = ' . $personalIdLabelRequired . ';
                            if (legalLabel) legalLabel.innerHTML = ' . $legalIdLabel . ';
                        }
                    }
                    
                    // Initial state
                    toggleIdFields();
                    
                    // Listen for changes
                    clientTypeSelect.addEventListener("change", toggleIdFields);
                });
                </script>
            '
        ]);

        $this->crud->setValidation();
    }

    /**
     * Cabinet login is not a clients-table column. The password is written to
     * client_accounts after save, and only when this form posted the toggle.
     */
    protected function addCabinetFields(): void
    {
        $entryId = $this->crud->getCurrentEntryId() ?: null;
        $account = $entryId
            ? ClientAccount::query()->where('client_id', $entryId)->first()
            : null;

        $host = e((string) config('cabinet.host'));
        $loginUrl = 'https://'.$host.'/login';
        $lastLogin = '';

        if ($account) {
            $when = $account->last_login_at
                ? $account->last_login_at->timezone(config('app.timezone'))->format('Y-m-d H:i')
                : __('client.cabinet.never_logged_in');
            $lastLogin = '<p class="help-block mb-2">'.e(__('client.cabinet.last_login')).': '.e($when).'</p>';
        }

        CRUD::addField([
            'name' => 'cabinet_heading',
            'type' => 'custom_html',
            'value' => '<hr><h3 class="mb-1">'.e(__('client.cabinet.heading')).'</h3>'
                .'<p class="text-muted">'.e(__('client.cabinet.intro')).' '
                .'<a href="'.$loginUrl.'" target="_blank" rel="noopener noreferrer">'.$host.'</a></p>'
                .$lastLogin,
        ]);

        CRUD::addField([
            'name' => 'cabinet_enabled',
            'label' => __('client.cabinet.enabled'),
            'type' => 'checkbox',
            'value' => $account?->is_active ? 1 : 0,
            'validationRules' => 'required|in:0,1',
            'wrapper' => [
                'class' => 'form-group col-md-12',
            ],
        ]);

        CRUD::addField([
            'name' => 'cabinet_password',
            'label' => __('client.cabinet.password'),
            'type' => 'password',
            'validationRules' => [
                Rule::requiredIf(function () use ($entryId) {
                    if (! request()->boolean('cabinet_enabled')) {
                        return false;
                    }

                    if (! $entryId) {
                        return true;
                    }

                    return ! ClientAccount::query()->where('client_id', $entryId)->exists();
                }),
                'nullable',
                'string',
                'min:8',
                'max:255',
                'confirmed',
            ],
            'validationMessages' => [
                'required' => __('client.cabinet.password_required'),
                'required_if' => __('client.cabinet.password_required'),
                'min' => __('client.cabinet.password_min'),
                'confirmed' => __('client.cabinet.password_confirmed'),
            ],
            'validationAttribute' => __('client.cabinet.password'),
            'hint' => __('client.cabinet.password_hint'),
            'attributes' => [
                'autocomplete' => 'new-password',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'cabinet_password_confirmation',
            'label' => __('client.cabinet.password_confirmation'),
            'type' => 'password',
            'attributes' => [
                'autocomplete' => 'new-password',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
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

        $entryId = $this->crud->getCurrentEntryId();
        if ($entryId) {
            $this->crud->modifyField('personal_id', [
                'validationRules' => 'required_if:client_type,0|nullable|string|max:255|unique:clients,personal_id,' . $entryId,
            ]);
            $this->crud->modifyField('email', [
                'validationRules' => 'nullable|email|unique:clients,email,' . $entryId,
            ]);
            $this->crud->modifyField('phone_number', [
                'validationRules' => 'required|unique:clients,phone_number,' . $entryId,
            ]);

            // Field rules are cached when first added in setupCreateOperation; rebuild after modifying.
            $this->crud->unsetValidation();
            $this->crud->setValidation();
        }
    }

    /**
     * Create a client via AJAX request.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAjax()
    {
        $request = request();
        
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_type' => 'required|in:0,1',
            'personal_id' => 'required_if:client_type,0|nullable|string|max:255|unique:clients,personal_id',
            'legal_id' => 'required_if:client_type,1|nullable|string|max:255',
            'address' => 'required|string',
            'email' => 'nullable|email|max:255|unique:clients,email',
            'phone_number' => 'required|string|max:255|unique:clients,phone_number',
        ]);

        try {
            $client = \App\Models\Client::create($validated);
            
            return response()->json([
                'success' => true,
                'client' => [
                    'id' => $client->id,
                    'name_with_id' => $client->name_with_id
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('client.messages.create_failed', ['error' => $e->getMessage()])
            ], 422);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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

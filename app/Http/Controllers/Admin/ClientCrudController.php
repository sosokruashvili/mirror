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
        CRUD::setEntityNameStrings('client', 'clients');
        
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
            'name' => 'phone_number',
            'label' => 'Phone',
            'type' => 'phone',
        ]);

        
        // Add client type column
        CRUD::addColumn([
            'name' => 'client_type',
            'label' => 'Type',
            'type' => 'boolean',
            'options' => [0 => 'Individual', 1 => 'Legal'],
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    return $entry->client_type ? 'badge badge-success' : 'badge badge-info';
                }
            ]
        ]);
        
        CRUD::setFromDb();

        // Add Filters
        CRUD::addFilter([
            'name' => 'client_type',
            'type' => 'select2',
            'label' => 'Client Type'
        ], function() {
            return [
                0 => 'Individual',
                1 => 'Legal',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'client_type', $value);
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'name',
            'label' => 'Name'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'name', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'email',
            'label' => 'Email'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'email', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'phone_number',
            'label' => 'Phone'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'phone_number', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'address',
            'label' => 'Address'
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
            'label' => 'Client Type',
            'type' => 'select_from_array',
            'options' => [
                0 => 'Individual',
                1 => 'Legal'
            ],
            'default' => 0,
            'hint' => 'Select client type: Individual or Legal',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'validationRules' => 'required',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'personal_id',
            'label' => 'Personal ID',
            'type' => 'text',
            'validationRules' => 'required_if:client_type,0',
            'wrapper' => [
                'class' => 'form-group col-md-6 personal-id-field',
                'style' => 'display: none;'
            ],
            'attributes' => [
                'placeholder' => 'Enter personal ID number',
            ]
        ]);

        CRUD::addField([
            'name' => 'address',
            'label' => 'Address',
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
            'label' => 'Legal ID',
            'type' => 'text',
            'validationRules' => 'required_if:client_type,1',
            'wrapper' => [
                'class' => 'form-group col-md-6 legal-id-field',
                'style' => 'display: none;'
            ],
            'attributes' => [
                'placeholder' => 'Enter legal ID number'
            ]
        ]);


        CRUD::addField([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'validationRules' => 'nullable|email',
            'wrapper' => [
                'class' => 'form-group col-md-6 email-field'
            ]
        ]);


        CRUD::addField([   // phone
            'name'  => 'phone_number', // db column for phone
            'label' => 'Phone',
            'type'  => 'phone',
            'validationRules' => 'required',
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
                            if (personalLabel) personalLabel.innerHTML = "Personal ID";
                            if (legalLabel) legalLabel.innerHTML = "Legal ID <span class=\"text-danger\">*</span>";
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
                            if (personalLabel) personalLabel.innerHTML = "Personal ID <span class=\"text-danger\">*</span>";
                            if (legalLabel) legalLabel.innerHTML = "Legal ID";
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
    }
}

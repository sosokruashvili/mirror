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
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'personal_id',
            'label' => 'Personal ID',
            'type' => 'text',
            'wrapper' => [
                'class' => 'form-group col-md-6 personal-id-field',
                'style' => 'display: none;'
            ],
            'attributes' => [
                'placeholder' => 'Enter personal ID number'
            ]
        ]);

        CRUD::addField([
            'name' => 'address',
            'label' => 'Address',
            'type' => 'textarea',
            'wrapper' => [
                'class' => 'form-group col-md-12'
            ]
        ]);

        
        CRUD::addField([
            'name' => 'legal_id',
            'label' => 'Legal ID',
            'type' => 'text',
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
            'wrapper' => [
                'class' => 'form-group col-md-6 email-field'
            ]
        ]);


        CRUD::addField([   // phone
            'name'  => 'phone_number', // db column for phone
            'label' => 'Phone',
            'type'  => 'phone',
            'wrapper' => [
                'class' => 'form-group col-md-6 phone-number-field'
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
                    
                    function toggleIdFields() {
                        if (clientTypeSelect.value == "1") {
                            // Legal client - show legal ID, hide personal ID
                            personalIdField.style.display = "none";
                            legalIdField.style.display = "block";
                            document.querySelector(\'input[name="personal_id"]\').value = "";
                        } else {
                            // Individual client - show personal ID, hide legal ID
                            personalIdField.style.display = "block";
                            legalIdField.style.display = "none";
                            document.querySelector(\'input[name="legal_id"]\').value = "";
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

// Supplier picker on the expense form: only shown when the selected expense
// category has suppliers attached, and limited to those suppliers.
(function () {
    var supplierOptions = window.cashierExpenseSupplierOptions || {};

    function suppliersFor(categoryId) {
        if (!categoryId) {
            return [];
        }

        return supplierOptions[String(categoryId)] || [];
    }

    function getCategoryId() {
        try {
            return crud.field('category_id').value;
        } catch (e) {
            return $('select[name="category_id"]').val();
        }
    }

    function getSupplierInput() {
        try {
            return crud.field('supplier_id').$input;
        } catch (e) {
            return $('select[name="supplier_id"]');
        }
    }

    function getSupplierWrapper() {
        var $wrapper = $('[bp-field-name="supplier_id"][bp-field-wrapper]').first();

        return $wrapper.length ? $wrapper : getSupplierInput().closest('.form-group');
    }

    function populateSuppliers(suppliers) {
        var $input = getSupplierInput();
        if (!$input.length) {
            return;
        }

        var previous = String($input.val() || '');

        $input.empty().append($('<option>').val('').text('-'));

        suppliers.forEach(function (supplier) {
            $input.append($('<option>').val(supplier.id).text(supplier.name));
        });

        // Keep the saved/selected supplier if it belongs to the new category.
        var stillValid = suppliers.some(function (supplier) {
            return String(supplier.id) === previous;
        });

        $input.val(stillValid ? previous : '').trigger('change');
    }

    function refreshSupplierField() {
        var suppliers = suppliersFor(getCategoryId());
        var $wrapper = getSupplierWrapper();

        populateSuppliers(suppliers);
        $wrapper.toggleClass('d-none', suppliers.length === 0);
    }

    function bind() {
        try {
            crud.field('category_id').onChange(refreshSupplierField);
        } catch (e) {
            $('select[name="category_id"]').on('change', refreshSupplierField);
        }

        refreshSupplierField();
    }

    $(document).ready(function () {
        // Give Backpack a moment to register its fields on create/edit.
        setTimeout(bind, 100);
    });
})();

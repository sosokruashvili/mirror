// Category-driven fields on the expense form:
//  - Supplier appears only when the selected category has suppliers attached,
//    limited to those suppliers.
//  - Product appears only for categories under საწარმოო.
(function () {
    var supplierOptions = window.cashierExpenseSupplierOptions || {};
    var productionCategoryIds = (window.cashierExpenseProductionCategoryIds || []).map(String);

    function suppliersFor(categoryId) {
        if (!categoryId) {
            return [];
        }

        return supplierOptions[String(categoryId)] || [];
    }

    function isProductionCategory(categoryId) {
        return !!categoryId && productionCategoryIds.indexOf(String(categoryId)) !== -1;
    }

    function getCategoryId() {
        try {
            return crud.field('category_id').value;
        } catch (e) {
            return $('select[name="category_id"]').val();
        }
    }

    function getInput(name) {
        try {
            return crud.field(name).$input;
        } catch (e) {
            return $('select[name="' + name + '"]');
        }
    }

    function getWrapper(name) {
        var $wrapper = $('[bp-field-name="' + name + '"][bp-field-wrapper]').first();

        return $wrapper.length ? $wrapper : getInput(name).closest('.form-group');
    }

    function populateSuppliers(suppliers) {
        var $input = getInput('supplier_id');
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

        populateSuppliers(suppliers);
        getWrapper('supplier_id').toggleClass('d-none', suppliers.length === 0);
    }

    function refreshProductField() {
        var show = isProductionCategory(getCategoryId());
        var $input = getInput('product_id');

        if (!show && $input.length && $input.val()) {
            $input.val('').trigger('change');
        }

        getWrapper('product_id').toggleClass('d-none', !show);
    }

    function refreshFields() {
        refreshSupplierField();
        refreshProductField();
    }

    function bind() {
        try {
            crud.field('category_id').onChange(refreshFields);
        } catch (e) {
            $('select[name="category_id"]').on('change', refreshFields);
        }

        refreshFields();
    }

    $(document).ready(function () {
        // Give Backpack a moment to register its fields on create/edit.
        setTimeout(bind, 100);
    });
})();

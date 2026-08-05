// Category-driven fields on the expense form:
//  - Supplier appears only when the selected category has suppliers attached.
//  - Product and purchase price appear only for categories under საწარმოო.
//  - Purchase price is filled from Supplier Prices for the selected pair.
(function () {
    var supplierOptions = window.cashierExpenseSupplierOptions || {};
    var productionCategoryIds = (window.cashierExpenseProductionCategoryIds || []).map(String);
    var supplierPrices = window.cashierExpenseSupplierPrices || {};
    var initializing = false;

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
            return $('input[name="' + name + '"], select[name="' + name + '"]').first();
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

        return show;
    }

    function configuredPrice() {
        var supplierId = String(getInput('supplier_id').val() || '');
        var productId = String(getInput('product_id').val() || '');
        var key = supplierId + ':' + productId;

        return supplierId && productId && Object.prototype.hasOwnProperty.call(supplierPrices, key)
            ? supplierPrices[key]
            : null;
    }

    function refreshPriceField(force) {
        var show = isProductionCategory(getCategoryId());
        var $input = getInput('price_usd');

        getWrapper('price_usd').toggleClass('d-none', !show);

        if (!show) {
            $input.val('');
            return;
        }

        // On initial edit-page load, keep the stored historical price. When the
        // supplier/product changes (or the field is blank), use today's configured price.
        if (force || !$input.val()) {
            $input.val(configuredPrice() || '').trigger('change');
        }
    }

    function refreshFields(forcePrice) {
        refreshSupplierField();
        refreshProductField();
        refreshPriceField(forcePrice);
    }

    function bind() {
        try {
            crud.field('category_id').onChange(function () {
                refreshFields(true);
            });
            crud.field('supplier_id').onChange(function () {
                refreshPriceField(!initializing);
            });
            crud.field('product_id').onChange(function () {
                refreshPriceField(!initializing);
            });
        } catch (e) {
            $('select[name="category_id"]').on('change', function () {
                refreshFields(true);
            });
            $('select[name="supplier_id"], select[name="product_id"]').on('change', function () {
                refreshPriceField(!initializing);
            });
        }

        initializing = true;
        refreshFields(false);
        initializing = false;
    }

    $(document).ready(function () {
        // Give Backpack a moment to register its fields on create/edit.
        setTimeout(bind, 100);
    });
})();

/**
 * Nested Pieces → Services UI for the Order create/edit form.
 *
 * Replaces the old flat `pieces` and `services` Backpack repeatables. Each piece is a
 * card that owns its own list of services. On submit we stamp the SAME flat field names
 * the controller already expects:
 *
 *     pieces[i][id|width|height|quantity]
 *     services[j][service_id|piece_id|...pivot fields]
 *
 * with services[j][piece_id] = "temp_i" (index of the parent piece card), matching the
 * temp-id map in OrderCrudController::store()/update().
 *
 * Service-only orders (product_type = 'service') hide the piece cards and use the
 * order-level services list instead (services with an empty piece_id).
 */
(function () {
    'use strict';

    var DATA = window.orderPiecesServicesData || { services: [], initialPieces: [], initialOrderServices: [] };

    // serviceId -> [extra field names]
    var serviceExtraMap = {};
    (DATA.services || []).forEach(function (s) {
        serviceExtraMap[String(s.id)] = s.extra_field_names || [];
    });

    // Every possible extra sub-field (the templates carry one wrapper per field).
    var ALL_SUB_FIELDS = ['quantity', 'perimeter', 'color', 'light_type', 'distance', 'length_cm',
        'area', 'tape_length', 'antifog_type', 'sensor_quantity1', 'sensor_type', 'foam_length'];

    // Pivot fields we carry through when hydrating a service row.
    var PIVOT_FIELDS = ['quantity', 'description', 'color', 'light_type', 'price_gel', 'distance',
        'length_cm', 'perimeter', 'area', 'antifog_type', 'foam_length', 'tape_length',
        'sensor_type', 'sensor_quantity1'];

    var expensesManuallyEdited = false;

    /* ----------------------------------------------------------------- helpers */

    function svcInput($row, field) { return $row.find('[data-svc-field="' + field + '"]'); }
    function getSvc($row, field) { return svcInput($row, field).val(); }
    function setSvc($row, field, val) { svcInput($row, field).val(val); }
    function subVisible($row, field) {
        var $w = svcInput($row, field).closest('.form-group');
        return $w.length > 0 && !$w.hasClass('d-none');
    }

    function initServiceSelect($select) {
        try {
            if ($.fn.select2) {
                $select.select2({ width: '100%', placeholder: 'Select a service', allowClear: true });
            }
        } catch (e) { /* plain <select> still works */ }
    }

    /* --------------------------------------------------------------- templates */

    function buildPieceCard() {
        var tpl = document.getElementById('ps-piece-card-template');
        return $(tpl.content.firstElementChild.cloneNode(true));
    }

    function buildServiceRow() {
        var tpl = document.getElementById('ps-service-row-template');
        return $(tpl.content.firstElementChild.cloneNode(true));
    }

    function renumberPieces() {
        $('#ps-pieces-container [data-piece-card]').each(function (i) {
            $(this).find('[data-piece-num]').text(i + 1);
        });
    }

    /* ------------------------------------------------------------ service rows */

    function addServiceRow($container, data) {
        var $row = buildServiceRow();
        $container.append($row);
        var $select = svcInput($row, 'service_id');
        initServiceSelect($select);

        if (data) {
            $select.val(data.service_id ? String(data.service_id) : '').trigger('change.select2');
            PIVOT_FIELDS.forEach(function (f) {
                if (data[f] !== undefined && data[f] !== null) {
                    setSvc($row, f, data[f]);
                }
            });
            if (data.service_id) {
                applyServiceFields($row, String(data.service_id), false);
            }
        }
        return $row;
    }

    // Show/hide the extra fields relevant to the selected service, reveal price + calc button.
    function applyServiceFields($row, serviceId, autofill) {
        // hide all extras first
        ALL_SUB_FIELDS.forEach(function (f) { svcInput($row, f).closest('.form-group').addClass('d-none'); });

        if (!serviceId) {
            $row.find('.svc-price, .svc-calc-btn').addClass('d-none');
            return;
        }

        var extras = serviceExtraMap[String(serviceId)] || [];
        extras.forEach(function (f) { svcInput($row, f).closest('.form-group').removeClass('d-none'); });
        $row.find('.svc-price, .svc-calc-btn').removeClass('d-none');

        if (autofill) {
            fillPieceMetrics($row, true);
        }
    }

    // Fill perimeter / area / tape length / foam length from the parent piece's dimensions.
    function fillPieceMetrics($row, overwrite) {
        var $card = $row.closest('[data-piece-card]');
        if (!$card.length) { return; } // order-level service, no piece

        var w = parseFloat($card.find('[data-piece-field="width"]').val());
        var h = parseFloat($card.find('[data-piece-field="height"]').val());
        var q = parseInt($card.find('[data-piece-field="quantity"]').val(), 10) || 1;
        if (isNaN(w) || isNaN(h)) { return; }

        var perUnitPerimeter = 2 * (w + h) / 100;
        var perUnitArea = w * h / 10000;

        // Show the TOTAL for the piece (per-unit metric × quantity).
        setSvc($row, 'perimeter', (perUnitPerimeter * q).toFixed(2));
        setSvc($row, 'area', (perUnitArea * q).toFixed(2));

        ['tape_length', 'foam_length'].forEach(function (field) {
            if (subVisible($row, field)) {
                var cur = getSvc($row, field);
                var hasValue = cur !== null && String(cur).trim() !== '';
                if (overwrite || !hasValue) {
                    setSvc($row, field, (perUnitPerimeter * q).toFixed(2));
                }
            }
        });
    }

    /* -------------------------------------------------------------- piece cards */

    function addPieceCard(data) {
        var $card = buildPieceCard();
        $('#ps-pieces-container').append($card);

        if (data) {
            if (data.id) { $card.find('[data-piece-field="id"]').val(data.id); }
            $card.find('[data-piece-field="width"]').val(data.width != null ? data.width : '');
            $card.find('[data-piece-field="height"]').val(data.height != null ? data.height : '');
            $card.find('[data-piece-field="quantity"]').val(data.quantity != null ? data.quantity : '');

            (data.services || []).forEach(function (svc) {
                addServiceRow($card.find('[data-services-container]'), svc);
            });
        }

        renumberPieces();
        return $card;
    }

    /* --------------------------------------------------------------- price calc */

    function getEligibleProductsCount() {
        var count = 0;
        $('[data-repeatable-identifier="products"][data-row-number]').each(function () {
            var $sel = $(this).find('select[name*="[product_id]"]');
            var val = $sel.val();
            if (val) {
                var type = ($sel.find('option[value="' + val + '"]').attr('data-product-type') || '').toLowerCase();
                if (type === 'glass' || type === 'mirror') { count++; }
            }
        });
        return count;
    }

    function pieceQuantityFor($row) {
        var $card = $row.closest('[data-piece-card]');
        if (!$card.length) { return 1; }
        return parseInt($card.find('[data-piece-field="quantity"]').val(), 10) || 1;
    }

    function calculateRowPrice($row) {
        var serviceId = getSvc($row, 'service_id');
        if (!serviceId) { alert('Please select a service first'); return; }

        var formData = { service_id: serviceId };
        // Only send values from currently visible sub-fields (mirrors the old behaviour).
        $row.find('.form-group:not(.d-none) [data-svc-field]').each(function () {
            var name = $(this).attr('data-svc-field');
            if (name && name !== 'service_id') { formData[name] = $(this).val(); }
        });

        // perimeter/area are shown as piece totals, but the price is computed from a per-unit
        // metric that then gets scaled by the piece quantity below. Feed the endpoint the
        // per-unit value so the final price stays identical to how it has always been computed.
        var qty = pieceQuantityFor($row);
        if (qty > 1) {
            if (formData.perimeter) { formData.perimeter = parseFloat(formData.perimeter) / qty; }
            if (formData.area) { formData.area = parseFloat(formData.area) / qty; }
        }

        $.ajax({
            url: '/admin/order/calculate-service-price',
            method: 'POST',
            data: formData,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() },
            success: function (response) {
                if (response.price_gel !== undefined) {
                    var pieceQty = pieceQuantityFor($row);
                    var products = getEligibleProductsCount() || 1;
                    setSvc($row, 'price_gel', response.price_gel * pieceQty * products);
                }
            }
        });
    }

    /* ----------------------------------------------------------------- expenses */

    function calculateExpenses() {
        if (expensesManuallyEdited) { return; }
        var total = 0;
        $('#ps-pieces-container [data-piece-card]').each(function () {
            var w = parseFloat($(this).find('[data-piece-field="width"]').val());
            var h = parseFloat($(this).find('[data-piece-field="height"]').val());
            var q = parseInt($(this).find('[data-piece-field="quantity"]').val(), 10) || 1;
            if (!isNaN(w) && !isNaN(h)) { total += (w / 100) * (h / 100) * q; }
        });

        // Add product offcut % (max across selected products) on top of piece area.
        var offcutPercent = 0;
        $('[data-repeatable-identifier="products"][data-row-number]').each(function () {
            var $select = $(this).find('select[name*="[product_id]"]');
            var $opt = $select.find('option:selected');
            var pct = parseFloat($opt.attr('data-offcut'));
            if (!isNaN(pct) && pct > offcutPercent) {
                offcutPercent = pct;
            }
        });
        if (offcutPercent > 0) {
            total += total * (offcutPercent / 100);
        }

        $('input[name="expenses"]').val(total > 0 ? total.toFixed(2) : '');
    }

    /* ------------------------------------------------------- section visibility */

    function currentProductType() {
        return $('select[name="product_type"], input[name="product_type"]').val();
    }

    // Toggle between piece cards (normal) and the order-level services list (service mode).
    // Also flips `required` on piece inputs so hidden cards don't block native submit.
    function updateSectionVisibility() {
        var isService = currentProductType() === 'service';
        $('#ps-pieces-section').toggleClass('d-none', isService);
        $('#ps-order-services-section').toggleClass('d-none', !isService);

        $('#ps-pieces-container [data-piece-field="width"], ' +
          '#ps-pieces-container [data-piece-field="height"], ' +
          '#ps-pieces-container [data-piece-field="quantity"]')
            .prop('required', !isService);
    }

    /* ------------------------------------------------------------ submit naming */

    function nameServiceRow($row, index, pieceIdVal) {
        var serviceId = getSvc($row, 'service_id');
        if (!serviceId) { return false; } // skip blank rows

        svcInput($row, 'service_id').attr('name', 'services[' + index + '][service_id]');

        // piece_id travels in a dedicated hidden input
        $row.find('[data-piece-id-input]').remove();
        $('<input type="hidden" data-piece-id-input>')
            .attr('name', 'services[' + index + '][piece_id]')
            .val(pieceIdVal)
            .appendTo($row);

        $row.find('[data-svc-field]').each(function () {
            var field = $(this).attr('data-svc-field');
            if (field === 'service_id') { return; }
            if (!$(this).closest('.form-group').hasClass('d-none')) {
                $(this).attr('name', 'services[' + index + '][' + field + ']');
            }
        });
        return true;
    }

    function namePieceCard($card, index) {
        $card.find('[data-piece-field]').each(function () {
            var field = $(this).attr('data-piece-field');
            $(this).attr('name', 'pieces[' + index + '][' + field + ']');
        });
    }

    function prepareForSubmit() {
        // Clear any names/inputs from a previous submit attempt so indexes stay clean.
        $('#pieces-services-field [data-piece-field], #pieces-services-field [data-svc-field]').removeAttr('name');
        $('#pieces-services-field [data-piece-id-input]').remove();

        var serviceIndex = 0;

        if (currentProductType() === 'service') {
            $('#ps-order-services-container [data-service-row]').each(function () {
                if (nameServiceRow($(this), serviceIndex, '')) { serviceIndex++; }
            });
        } else {
            $('#ps-pieces-container [data-piece-card]').each(function (i) {
                var $card = $(this);
                namePieceCard($card, i);
                $card.find('[data-services-container] [data-service-row]').each(function () {
                    if (nameServiceRow($(this), serviceIndex, 'temp_' + i)) { serviceIndex++; }
                });
            });
        }
    }

    /* ------------------------------------------------------------------- events */

    $(function () {
        // Hydrate from initial data (old input / edit / empty).
        (DATA.initialPieces || []).forEach(function (p) { addPieceCard(p); });
        if (!(DATA.initialPieces || []).length) { addPieceCard(); } // create → 1 empty card
        (DATA.initialOrderServices || []).forEach(function (s) {
            addServiceRow($('#ps-order-services-container'), s);
        });

        updateSectionVisibility();
        calculateExpenses();

        // Add / remove piece cards
        $(document).on('click', '#ps-add-piece-btn', function () {
            addPieceCard();
            calculateExpenses();
        });
        $(document).on('click', '[data-remove-piece]', function () {
            var $cards = $('#ps-pieces-container [data-piece-card]');
            if ($cards.length <= 1) {
                // keep at least one card; just clear it
                var $c = $(this).closest('[data-piece-card]');
                $c.find('[data-piece-field]').val('');
                $c.find('[data-services-container]').empty();
            } else {
                $(this).closest('[data-piece-card]').remove();
            }
            renumberPieces();
            calculateExpenses();
        });

        // Add / remove services
        $(document).on('click', '[data-add-service]', function () {
            addServiceRow($(this).closest('[data-piece-card]').find('[data-services-container]'));
        });
        $(document).on('click', '.ps-add-order-service-btn', function () {
            addServiceRow($('#ps-order-services-container'));
        });
        $(document).on('click', '[data-remove-service]', function () {
            $(this).closest('[data-service-row]').remove();
        });

        // Service selection → show relevant fields + autofill from piece
        $(document).on('change', '#pieces-services-field [data-svc-field="service_id"]', function () {
            applyServiceFields($(this).closest('[data-service-row]'), $(this).val(), true);
        });

        // Piece dimensions change → refresh metrics for its services + expenses (live, on every keystroke)
        $(document).on('input change',
            '#ps-pieces-container [data-piece-field="width"], ' +
            '#ps-pieces-container [data-piece-field="height"], ' +
            '#ps-pieces-container [data-piece-field="quantity"]', function () {
            var $card = $(this).closest('[data-piece-card]');
            $card.find('[data-service-row]').each(function () {
                if (getSvc($(this), 'service_id')) { fillPieceMetrics($(this), true); }
            });
            calculateExpenses();
        });

        // On commit (blur/change) of a piece dimension or quantity, keep the price of any
        // already-priced service in sync — the piece quantity multiplies into the price.
        $(document).on('change',
            '#ps-pieces-container [data-piece-field="width"], ' +
            '#ps-pieces-container [data-piece-field="height"], ' +
            '#ps-pieces-container [data-piece-field="quantity"]', function () {
            $(this).closest('[data-piece-card]').find('[data-service-row]').each(function () {
                var $row = $(this);
                if (getSvc($row, 'service_id') && String(getSvc($row, 'price_gel')).trim() !== '') {
                    calculateRowPrice($row);
                }
            });
        });

        // Calculate price
        $(document).on('click', '#pieces-services-field .calculate-price-btn', function () {
            calculateRowPrice($(this).closest('[data-service-row]'));
        });

        // Product type toggles which section is active
        $(document).on('change', 'select[name="product_type"], input[name="product_type"]', updateSectionVisibility);

        // Respect a manual expenses override
        $(document).on('input', 'input[name="expenses"]', function () { expensesManuallyEdited = true; });

        // Product selection (and its offcut %) changed — recalc expenses
        $(document).on('order-products-changed', calculateExpenses);
        $(document).on('change', '[data-repeatable-identifier="products"] select[name*="[product_id]"]', calculateExpenses);

        // Stamp the flat pieces[]/services[] names just before the form is submitted.
        $('form').on('submit', prepareForSubmit);
    });
})();

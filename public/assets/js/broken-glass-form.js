/*
 * Break-detail fields in the team's "გატყდა" dialogs
 * (resources/views/admin/partials/broken-glass-fields.blade.php).
 *
 * The page owns its modal and the request; this only resets the fields for a
 * sheet, checks them before sending, and shows an error inside the dialog.
 * The server repeats every check (App\Support\BrokenGlass\BreakDetails) with the
 * same Georgian messages, so a message the server sends back is shown the same way.
 */
(function (window) {
    'use strict';

    function find(root, selector) {
        return root ? root.querySelector(selector) : null;
    }

    function formatArea(value) {
        var number = Number(value);
        if (!isFinite(number)) return '—';
        return String(Math.round(number * 10000) / 10000);
    }

    function showError(root, message) {
        var box = find(root, '[data-bg-error]');
        if (!box) {
            if (message) alert(message);
            return;
        }
        box.textContent = message || '';
        box.classList.toggle('d-none', !message);
    }

    function selectedMachineNeedsComment(root) {
        var select = find(root, '[data-bg-machine]');
        var option = select && select.selectedOptions ? select.selectedOptions[0] : null;
        return !!(option && option.getAttribute('data-requires-comment') === '1');
    }

    // The comment label lives in the page's modal; mark it required for "სხვა".
    function syncCommentLabel(root, descriptionEl) {
        if (!descriptionEl) return;
        var label = descriptionEl.id ? document.querySelector('label[for="' + descriptionEl.id + '"]') : null;
        if (!label) return;
        if (!label.hasAttribute('data-bg-optional-text')) {
            label.setAttribute('data-bg-optional-text', label.textContent);
        }
        label.textContent = selectedMachineNeedsComment(root)
            ? 'კომენტარი (სავალდებულო)'
            : label.getAttribute('data-bg-optional-text');
    }

    var BrokenGlassForm = {
        /**
         * Empty the fields for a sheet of `unitArea` m² (0 or missing = unknown size).
         */
        reset: function (root, unitArea, descriptionEl) {
            if (!root) return;
            var area = Number(unitArea);
            root.setAttribute('data-unit-area', isFinite(area) && area > 0 ? String(area) : '');

            var areaEl = find(root, '[data-bg-sheet-area]');
            if (areaEl) areaEl.textContent = isFinite(area) && area > 0 ? formatArea(area) : '—';

            ['[data-bg-usable]', '[data-bg-machine]', '[data-bg-stage]'].forEach(function (selector) {
                var field = find(root, selector);
                if (field) field.value = '';
            });
            if (descriptionEl) descriptionEl.value = '';

            showError(root, '');

            if (!root.hasAttribute('data-bg-bound')) {
                root.setAttribute('data-bg-bound', '1');
                var machine = find(root, '[data-bg-machine]');
                if (machine) {
                    machine.addEventListener('change', function () {
                        syncCommentLabel(root, root._bgDescription);
                    });
                }
            }
            root._bgDescription = descriptionEl || null;
            syncCommentLabel(root, descriptionEl);
        },

        /**
         * Check the fields. Returns {error: '...'} or {fields: {...}} ready to post.
         */
        collect: function (root, descriptionEl) {
            var usableRaw = ((find(root, '[data-bg-usable]') || {}).value || '').trim().replace(',', '.');
            var machineId = (find(root, '[data-bg-machine]') || {}).value || '';
            var stageId = (find(root, '[data-bg-stage]') || {}).value || '';
            var description = descriptionEl ? descriptionEl.value.trim() : '';
            var unitArea = parseFloat(root ? root.getAttribute('data-unit-area') : '');

            if (usableRaw === '') {
                return { error: 'ჩაწერე გამოსადეგი მ². თუ არაფერია გამოსადეგი, ჩაწერე 0.' };
            }
            if (!/^\d+(\.\d+)?$/.test(usableRaw)) {
                return { error: 'გამოსადეგი მ² რიცხვი უნდა იყოს, მაგ. 0.35.' };
            }
            var usable = Number(usableRaw);
            if (isFinite(unitArea) && usable > unitArea + 0.00005) {
                return { error: 'გამოსადეგი მ² ფურცლის ფართობზე (' + formatArea(unitArea) + ' მ²) მეტი ვერ იქნება.' };
            }
            if (!machineId) {
                return { error: 'აირჩიე დანადგარი.' };
            }
            if (!stageId) {
                return { error: 'აირჩიე ეტაპი, რომელზეც დაზიანება აღმოაჩინეს.' };
            }
            if (selectedMachineNeedsComment(root) && !description) {
                return { error: 'ამ დანადგარისთვის კომენტარი სავალდებულოა.' };
            }

            return {
                fields: {
                    usable_area: usableRaw,
                    machine_id: machineId,
                    detected_stage_id: stageId,
                    description: description
                }
            };
        },

        showError: showError
    };

    window.BrokenGlassForm = BrokenGlassForm;
})(window);

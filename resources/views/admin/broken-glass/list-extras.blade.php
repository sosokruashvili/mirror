{{--
    Broken Glasses list: totals for the current filters (filled from the `totals`
    that BrokenGlassCrudController::search() adds to every table response) and the
    click handler for the Confirm / Undo buttons.

    Inline on purpose (not a basset block): basset pins stale copies on prod.
--}}
<div class="card mb-3" id="brokenGlassTotals">
    <div class="card-body py-2 d-flex flex-wrap align-items-baseline gap-4">
        <div>
            <span class="text-muted small">{{ __('broken_glass.totals.entries') }}</span>
            <strong class="ms-1" data-total="entries">…</strong>
        </div>
        <div>
            <span class="text-muted small">{{ __('broken_glass.totals.damaged') }}</span>
            <strong class="ms-1"><span data-total="damaged">…</span> მ²</strong>
        </div>
        <div>
            <span class="text-muted small">{{ __('broken_glass.totals.usable') }}</span>
            <strong class="ms-1 text-success"><span data-total="usable">…</span> მ²</strong>
        </div>
        <div>
            <span class="text-muted small">{{ __('broken_glass.totals.loss') }}</span>
            <strong class="ms-1 text-danger"><span data-total="loss">…</span> მ²</strong>
        </div>
        <div class="text-muted small ms-md-auto">{{ __('broken_glass.totals.hint') }}</div>
    </div>
</div>

@push('after_scripts')
<script>
(function () {
    var texts = {
        confirm: {
            title: @json(__('broken_glass.confirm_dialog.title')),
            textReturn: @json(__('broken_glass.confirm_dialog.text_return')),
            textNothing: @json(__('broken_glass.confirm_dialog.text_nothing')),
            button: @json(__('broken_glass.buttons.confirm'))
        },
        unconfirm: {
            title: @json(__('broken_glass.unconfirm_dialog.title')),
            text: @json(__('broken_glass.unconfirm_dialog.text')),
            button: @json(__('broken_glass.buttons.unconfirm'))
        },
        cancel: @json(trans('backpack::crud.cancel')),
        error: @json(trans('backpack::base.error')),
        failed: @json(__('broken_glass.errors.failed'))
    };

    function formatArea(value) {
        return Number(value || 0).toFixed(3);
    }

    $(document).on('xhr.dt', '#crudTable', function (e, settings, json) {
        if (!json || !json.totals) return;
        var box = document.getElementById('brokenGlassTotals');
        Object.keys(json.totals).forEach(function (key) {
            var el = box.querySelector('[data-total="' + key + '"]');
            if (el) el.textContent = key === 'entries' ? json.totals[key] : formatArea(json.totals[key]);
        });
    });

    function ask(title, text, button, danger) {
        if (typeof swal !== 'function') {
            return Promise.resolve(window.confirm(title + '\n\n' + text));
        }
        return swal({
            title: title,
            text: text,
            icon: danger ? 'warning' : 'info',
            buttons: {
                cancel: { text: texts.cancel, value: null, visible: true, className: 'bg-secondary', closeModal: true },
                confirm: { text: button, value: true, visible: true, className: danger ? 'bg-danger' : 'bg-success' }
            }
        });
    }

    function fail(message) {
        if (typeof swal === 'function') {
            swal({ title: texts.error, text: message, icon: 'error' });
        } else {
            alert(message);
        }
    }

    window.brokenGlassAction = function (button) {
        var action = button.getAttribute('data-action');
        var route = button.getAttribute('data-route');
        var dialog;

        if (action === 'confirm') {
            var usable = parseFloat(button.getAttribute('data-usable') || '0');
            dialog = ask(
                texts.confirm.title,
                usable > 0 ? texts.confirm.textReturn.replace(':area', button.getAttribute('data-usable')) : texts.confirm.textNothing,
                texts.confirm.button,
                false
            );
        } else {
            dialog = ask(texts.unconfirm.title, texts.unconfirm.text, texts.unconfirm.button, true);
        }

        dialog.then(function (ok) {
            if (!ok) return;
            $.ajax({
                url: route,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                    'Accept': 'application/json'
                },
                success: function (result) {
                    if (result && result.success) {
                        new Noty({ type: 'success', text: result.message }).show();
                        if (window.crud && crud.table) crud.table.ajax.reload(null, false);
                        return;
                    }
                    fail((result && result.message) || texts.failed);
                },
                error: function (xhr) {
                    fail((xhr.responseJSON && xhr.responseJSON.message) || texts.failed);
                }
            });
        });
    };
})();
</script>
@endpush

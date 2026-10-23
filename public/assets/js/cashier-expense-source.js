// The "paid from" choice only applies to cash expenses. Transfers never
// leave the cashier or the safe, so the field stays hidden for them.
(function () {
    function isCash() {
        try {
            return crud.field('type').value === 'Cash';
        } catch (e) {
            return $('select[name="type"]').val() === 'Cash';
        }
    }

    function sourceWrapper() {
        var $wrapper = $('[bp-field-name="source"][bp-field-wrapper]').first();

        return $wrapper.length ? $wrapper : $('select[name="source"]').closest('.form-group');
    }

    function refresh() {
        sourceWrapper().toggleClass('d-none', !isCash());
    }

    function bind() {
        refresh();

        try {
            crud.field('type').onChange(refresh);
        } catch (e) {
            $(document).on('change', 'select[name="type"]', refresh);
        }
    }

    $(document).ready(function () {
        setTimeout(bind, 100);
    });
})();

<script>
document.addEventListener('DOMContentLoaded', function () {
    var type = document.querySelector('select[name="type"]');
    var recipient = document.querySelector('.notification-recipient-field');
    var recipientInput = document.querySelector('input[name="recipient"]');
    var client = document.querySelector('.notification-client-field');
    var clientSelect = document.querySelector('select[name="client_id"]');
    var templateSelect = document.querySelector('[name="sms_template_id"]');
    var body = document.querySelector('textarea[name="body"]');
    var hint = document.querySelector('.notification-body-field .help-block');
    var cabinetType = @json($cabinetType);
    var smsHint = @json($smsHint);
    var cabinetHint = @json($cabinetHint);
    var templates = @json($templates);
    var clients = @json($clients);

    function selectedClient() {
        if (!clientSelect || clientSelect.value === '') {
            return null;
        }

        return clients[clientSelect.value] || null;
    }

    function applyPlaceholders(text) {
        var person = selectedClient();

        if (!person) {
            return text;
        }

        if (person.name) {
            text = text.split('{client_name}').join(person.name);
        }

        if (person.phone) {
            text = text.split('{client_phone}').join(person.phone);
        }

        return text;
    }

    function sync() {
        var cabinet = type && type.value === cabinetType;

        if (recipient) {
            recipient.style.display = cabinet ? 'none' : '';
        }

        if (hint) {
            hint.textContent = cabinet ? cabinetHint : smsHint;
        }

        if (window.jQuery && client) {
            window.jQuery(client).find('select').next('.select2-container').css('width', '100%');
        }
    }

    function fillPhone() {
        var cabinet = type && type.value === cabinetType;
        var person = selectedClient();

        if (!cabinet && person && person.phone && recipientInput) {
            recipientInput.value = person.phone;
        }
    }

    function fillFromTemplate() {
        if (!templateSelect || !body || templateSelect.value === appliedTemplate) {
            return;
        }

        appliedTemplate = templateSelect.value;
        var text = templates[templateSelect.value];

        if (typeof text !== 'string') {
            return;
        }

        body.value = applyPlaceholders(text);
    }

    function onClientChange() {
        if (!clientSelect || clientSelect.value === appliedClient) {
            return;
        }

        appliedClient = clientSelect.value;
        fillPhone();

        if (body && body.value.indexOf('{') !== -1) {
            body.value = applyPlaceholders(body.value);
        }
    }

    var appliedTemplate = templateSelect ? templateSelect.value : '';
    var appliedClient = clientSelect ? clientSelect.value : '';

    function bind(el, fn) {
        if (!el) {
            return;
        }

        el.addEventListener('change', fn);

        if (window.jQuery) {
            window.jQuery(el).on('change select2:select select2:clear', fn);
        }
    }

    bind(type, sync);
    bind(templateSelect, fillFromTemplate);
    bind(clientSelect, onClientChange);
    sync();
});
</script>

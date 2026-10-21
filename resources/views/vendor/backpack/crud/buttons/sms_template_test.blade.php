@if ($crud->hasAccess('update'))
    <a href="javascript:void(0)"
       onclick="openSmsTemplateTest(this)"
       bp-button="smsTemplateTest"
       class="btn btn-sm btn-link"
       data-button-type="sms-template-test"
       data-route="{{ route('sms-template.test', $entry->getKey()) }}"
       data-title="{{ $entry->title }}"
       data-text="{{ $entry->text }}">
        <i class="la la-paper-plane"></i> <span>{{ __('sms_template.test.button') }}</span>
    </a>
@endif

@push('after_scripts') @if (request()->ajax()) @endpush @endif
@bassetBlock('backpack/crud/buttons/sms-template-test-'.app()->getLocale().'.js')
<script>
    if (typeof openSmsTemplateTest !== 'function') {
        var smsTemplateTestSampleName = @json(__('sms_template.test.sample_name'));
        var smsTemplateTestSampleUrl = @json(\App\Models\SmsTemplate::testCabinetUrl());
        var smsTemplateTestSampleOrder = @json(\App\Models\SmsTemplate::TEST_ORDER_ID);
        var smsTemplateTestSubmitLabel = @json(__('sms_template.test.submit'));
        var smsTemplateTestWorkingLabel = @json(__('sms_template.test.working'));
        var smsTemplateTestFailedLabel = @json(__('sms_template.test.failed'));

        document.body.insertAdjacentHTML('beforeend', @json(view('crud::buttons.sms_template_test_modal')->render()));

        var smsTemplateTestModal = document.getElementById('smsTemplateTestModal');
        var smsTemplateTestPhone = document.getElementById('smsTemplateTestPhone');
        var smsTemplateTestPreview = document.getElementById('smsTemplateTestPreview');
        var smsTemplateTestName = document.getElementById('smsTemplateTestName');
        var smsTemplateTestError = document.getElementById('smsTemplateTestError');
        var smsTemplateTestSubmit = document.getElementById('smsTemplateTestSubmit');
        var smsTemplateTestSource = '';

        function smsTemplateTestRendered() {
            var phone = smsTemplateTestPhone.value.trim();
            var text = smsTemplateTestSource;

            text = text.split('{client_name}').join(smsTemplateTestSampleName);

            if (phone !== '') {
                text = text.split('{client_phone}').join(phone);
            }

            text = text.split('{cabinet_url}').join(smsTemplateTestSampleUrl);
            text = text.split('{order_id}').join(smsTemplateTestSampleOrder);

            return text;
        }

        function smsTemplateTestShowError(message) {
            if (!message) {
                smsTemplateTestError.classList.add('d-none');
                smsTemplateTestError.textContent = '';
                return;
            }

            smsTemplateTestError.textContent = message;
            smsTemplateTestError.classList.remove('d-none');
        }

        function openSmsTemplateTest(button) {
            smsTemplateTestModal.dataset.route = button.getAttribute('data-route') || '';
            smsTemplateTestSource = button.getAttribute('data-text') || '';
            smsTemplateTestName.textContent = button.getAttribute('data-title') || '';
            smsTemplateTestPhone.value = '';
            smsTemplateTestPreview.value = smsTemplateTestRendered();
            smsTemplateTestSubmit.disabled = false;
            smsTemplateTestSubmit.textContent = smsTemplateTestSubmitLabel;
            smsTemplateTestShowError('');
            bootstrap.Modal.getOrCreateInstance(smsTemplateTestModal).show();
        }

        smsTemplateTestPhone.addEventListener('input', function () {
            smsTemplateTestPreview.value = smsTemplateTestRendered();
        });

        document.getElementById('smsTemplateTestForm').addEventListener('submit', function (event) {
            event.preventDefault();
            smsTemplateTestShowError('');
            smsTemplateTestSubmit.disabled = true;
            smsTemplateTestSubmit.textContent = smsTemplateTestWorkingLabel;

            $.ajax({
                url: smsTemplateTestModal.dataset.route,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                    'Accept': 'application/json'
                },
                data: { phone: smsTemplateTestPhone.value.trim() },
                success: function (result) {
                    bootstrap.Modal.getOrCreateInstance(smsTemplateTestModal).hide();
                    smsTemplateTestSubmit.disabled = false;
                    smsTemplateTestSubmit.textContent = smsTemplateTestSubmitLabel;
                    new Noty({
                        type: 'success',
                        text: (result && result.message) ? result.message : smsTemplateTestSubmitLabel
                    }).show();
                },
                error: function (xhr) {
                    smsTemplateTestSubmit.disabled = false;
                    smsTemplateTestSubmit.textContent = smsTemplateTestSubmitLabel;
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.phone && xhr.responseJSON.errors.phone[0])))
                        ? (xhr.responseJSON.message || xhr.responseJSON.errors.phone[0])
                        : smsTemplateTestFailedLabel;
                    smsTemplateTestShowError(message);
                }
            });
        });
    }
</script>
@endBassetBlock
@if (!request()->ajax()) @endpush @endif

<div class="modal fade" id="smsTemplateTestModal" tabindex="-1" aria-labelledby="smsTemplateTestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="smsTemplateTestModalLabel">{{ __('sms_template.test.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('backpack::crud.cancel') }}"></button>
            </div>
            <form id="smsTemplateTestForm">
                <div class="modal-body">
                    <p class="fw-bold mb-2" id="smsTemplateTestName"></p>
                    <div class="mb-3">
                        <label class="form-label" for="smsTemplateTestPhone">{{ __('sms_template.test.phone') }}</label>
                        <input type="tel" class="form-control" id="smsTemplateTestPhone" name="phone" inputmode="tel" autocomplete="tel" maxlength="32" placeholder="{{ __('sms_template.test.phone_placeholder') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="smsTemplateTestPreview">{{ __('sms_template.test.preview') }}</label>
                        <textarea class="form-control" id="smsTemplateTestPreview" rows="4" readonly></textarea>
                    </div>
                    <p class="help-block mb-2">{{ __('sms_template.test.hint', ['name' => __('sms_template.test.sample_name'), 'order' => \App\Models\SmsTemplate::TEST_ORDER_ID]) }}</p>
                    <div id="smsTemplateTestError" class="alert alert-danger d-none mb-0" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('backpack::crud.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="smsTemplateTestSubmit">{{ __('sms_template.test.submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

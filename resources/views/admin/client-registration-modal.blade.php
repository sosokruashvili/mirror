<!-- Client Registration Modal -->
<div class="modal fade" id="clientRegistrationModal" tabindex="-1" aria-labelledby="clientRegistrationModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientRegistrationModalLabel">{{ __('client.modal.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('client.modal.close') }}"></button>
            </div>
            <form id="clientRegistrationForm" data-action="{{ url(config('backpack.base.route_prefix', 'admin') . '/client/create-ajax') }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">{{ __('client.client_type') }} <span class="text-danger">*</span></label>
                            <select name="client_type" class="form-control" required>
                                @foreach (__('client.types') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('client.name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3 personal-id-field">
                            <label class="form-label">{{ __('client.personal_id') }} <span class="text-danger">*</span></label>
                            <input type="text" name="personal_id" class="form-control" placeholder="{{ __('client.placeholders.personal_id') }}">
                        </div>
                        
                        <div class="col-md-6 mb-3 legal-id-field" style="display: none;">
                            <label class="form-label">{{ __('client.legal_id') }} <span class="text-danger">*</span></label>
                            <input type="text" name="legal_id" class="form-control" placeholder="{{ __('client.placeholders.legal_id') }}">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">{{ __('client.address') }} <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control" rows="2" required></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('client.email') }}</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('client.phone') }} <span class="text-danger">*</span></label>
                            <input type="tel" name="phone_number" class="form-control" required>
                        </div>
                    </div>
                    <div id="clientFormErrors" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('backpack::crud.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('client.modal.submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

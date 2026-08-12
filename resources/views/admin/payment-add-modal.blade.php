@php
    $clients = \App\Models\Client::all()->pluck('name_with_id', 'id');
    $defaultCurrencyRate = \App\Models\Currency::exchangeRate();
@endphp
<!-- Payment Add Modal -->
{{-- data-app-timezone: the JS re-stamps Payment Date each time the modal opens, and must
     format it in the application timezone rather than whatever the workstation is set to. --}}
<div class="modal fade" id="paymentAddModal" tabindex="-1" aria-labelledby="paymentAddModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true" data-app-timezone="{{ config('app.timezone') }}">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentAddModalLabel">{{ __('payment.modal.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="paymentAddForm" data-action="{{ url(config('backpack.base.route_prefix', 'admin') . '/payment/create-ajax') }}" data-balance-url="{{ url(config('backpack.base.route_prefix', 'admin') . '/payment/get-client-balance') }}" enctype="multipart/form-data">
                {{-- Set on the edit page (order already exists). On the create page it stays empty
                     and the payment is linked to the order right after it is saved. --}}
                <input type="hidden" name="order_id" id="paymentAddOrderId" value="{{ isset($entry) && $entry ? $entry->getKey() : '' }}">
                {{-- Regenerated in JS each time the modal opens; makes creation idempotent
                     so a double-submit cannot create two payments. --}}
                <input type="hidden" name="idempotency_key" id="paymentAddIdempotencyKey" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.client') }} <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-control" required id="paymentAddClientId">
                                <option value="">{{ __('payment.modal.select_client') }}</option>
                                @foreach($clients as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="paymentAddModal_balance_display" style="display: none;">
                            <label class="form-label" style="margin-bottom: 0;">{{ __('payment.client_balance') }}</label>
                            <div class="form-control payment-add-modal-balance-value" style="padding: 0.375rem 0.75rem; min-height: 38px; display: flex; align-items: center;">
                                <span id="paymentAddModal_balance_value" style="font-weight: 600; font-size: 1rem;">-</span>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.payment_method') }} <span class="text-danger">*</span></label>
                            {{-- No pre-selected method on purpose: it used to default to Cash, so a
                                 form submitted before the user got to this field saved a payment with
                                 the wrong method, which was then "fixed" by adding a second payment. --}}
                            <select name="method" class="form-control" required>
                                <option value="">{{ __('payment.modal.select_method') }}</option>
                                @foreach (\App\Models\Payment::methods() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.payment_type') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                @foreach(\App\Models\Payment::types() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.currency_rate') }} <span class="text-danger">*</span></label>
                            <input type="number" name="currency_rate" class="form-control" step="0.0001" min="0" required value="{{ $defaultCurrencyRate }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.amount_gel_field') }} <span class="text-danger">*</span></label>
                            <input type="number" name="amount_gel" class="form-control" step="0.01" min="0" required placeholder="0.00"> ₾
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.status') }} <span class="text-danger">*</span></label>
                            {{-- Same reason as Payment Method: a silent "Pending" default produced
                                 payments the user then re-created as "Paid". Make it an explicit choice. --}}
                            <select name="status" class="form-control" required>
                                <option value="">{{ __('payment.modal.select_status') }}</option>
                                @foreach (__('payment.statuses') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('payment.payment_date') }} <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="payment_date" class="form-control" required value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">{{ __('payment.payment_file') }}</label>
                            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">Upload payment related document (invoice, receipt, etc.)</small>
                        </div>
                    </div>
                    <div id="paymentFormErrors" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('backpack::crud.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('payment.modal.submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

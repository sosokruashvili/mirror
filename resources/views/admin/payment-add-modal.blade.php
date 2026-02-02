@php
    $clients = \App\Models\Client::all()->pluck('name_with_id', 'id');
    $defaultCurrencyRate = \App\Models\Currency::exchangeRate();
@endphp
<!-- Payment Add Modal -->
<div class="modal fade" id="paymentAddModal" tabindex="-1" aria-labelledby="paymentAddModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentAddModalLabel">New Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="paymentAddForm" data-action="{{ url(config('backpack.base.route_prefix', 'admin') . '/payment/create-ajax') }}" data-balance-url="{{ url(config('backpack.base.route_prefix', 'admin') . '/payment/get-client-balance') }}" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Client <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-control" required id="paymentAddClientId">
                                <option value="">Select Client</option>
                                @foreach($clients as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="paymentAddModal_balance_display" style="display: none;">
                            <label class="form-label" style="margin-bottom: 0;">Client Balance</label>
                            <div class="form-control payment-add-modal-balance-value" style="padding: 0.375rem 0.75rem; min-height: 38px; display: flex; align-items: center;">
                                <span id="paymentAddModal_balance_value" style="font-weight: 600; font-size: 1rem;">-</span>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="method" class="form-control" required>
                                <option value="Cash">Cash</option>
                                <option value="Transfer">Transfer</option>
                                <option value="Terminal">Terminal</option>
                                <option value="PM Transfer">PM Transfer</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency Rate <span class="text-danger">*</span></label>
                            <input type="number" name="currency_rate" class="form-control" step="0.0001" min="0" required value="{{ $defaultCurrencyRate }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount GEL <span class="text-danger">*</span></label>
                            <input type="number" name="amount_gel" class="form-control" step="0.01" min="0" required placeholder="0.00"> ₾
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="Paid">Paid</option>
                                <option value="Pending" selected>Pending</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="payment_date" class="form-control" required value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Payment File</label>
                            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">Upload payment related document (invoice, receipt, etc.)</small>
                        </div>
                    </div>
                    <div id="paymentFormErrors" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

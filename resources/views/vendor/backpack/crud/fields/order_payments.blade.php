{{--
    Custom field: the payments attached to this order + the "Add Payment" button.

    The order form used to show nothing but the button, so a payment that had already
    been added left no trace on the page — users re-added it (usually to correct a wrong
    method or status) and the order ended up with two payments. This field lists what is
    already there, with edit/delete links, so a correction never means "create another one".

    Rows are rendered by public/assets/js/payment-add-modal.js from the JSON below, so
    existing payments and ones added through the modal use the exact same markup.
--}}
@php
    $entry = $crud->getCurrentEntry();

    $existingPayments = $entry
        ? $entry->payments()->orderBy('id')->get()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount_gel' => (float) $payment->amount_gel,
                'method' => $payment->method,
                'status' => $payment->status,
                'type' => $payment->type,
                'payment_date' => optional($payment->payment_date)->format('d M Y H:i'),
            ];
        })->values()->all()
        : [];

    $user = backpack_user();
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{{ $field['label'] ?? 'Payments' }}</label>

    <div id="orderPaymentsField"
         data-payments="{{ json_encode($existingPayments) }}"
         data-payment-url="{{ url(config('backpack.base.route_prefix', 'admin') . '/payment') }}"
         data-can-update="{{ $user && $user->can('payment.update') ? '1' : '0' }}"
         data-can-delete="{{ $user && $user->can('payment.delete') ? '1' : '0' }}">

        <div id="orderPaymentsList" class="list-group mb-2"></div>

        <div id="orderPaymentsEmpty" class="text-muted small mb-2">
            {{ __('payment.no_payments_on_order') }}
        </div>

        @unless ($user && ($user->can('payment.update') || $user->can('payment.delete')))
            {{-- Without the delete/edit permission the only way this user can "fix" a payment
                 is by adding a corrected one, which is exactly how the duplicates happened.
                 Say plainly what to do instead. Toggled with the list by the JS. --}}
            <div id="orderPaymentsReadonlyNote" class="text-muted small mb-2" style="display: none;">
                Wrong payment? Ask an administrator to correct or remove it — adding a second
                payment leaves the wrong one on the order.
            </div>
        @endunless

        <button type="button" id="addPaymentBtn" class="btn btn-sm btn-outline-primary">
            <i class="la la-plus"></i> Add Payment
        </button>

        @if (isset($field['hint']))
            <p class="help-block mt-1">{!! $field['hint'] !!}</p>
        @endif
    </div>
@include('crud::fields.inc.wrapper_end')

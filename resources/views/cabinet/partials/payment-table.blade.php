<div class="table-responsive">
    <table class="table table-vcenter card-table">
        <thead>
            <tr>
                <th>{{ __('cabinet.date') }}</th>
                <th>{{ __('cabinet.amount') }}</th>
                <th>{{ __('cabinet.status') }}</th>
                <th>{{ __('cabinet.method') }}</th>
                <th>{{ __('cabinet.type') }}</th>
                <th>{{ __('cabinet.order') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                @include('cabinet.partials.payment-row', ['payment' => $payment])
            @empty
                <tr>
                    <td colspan="6" class="text-secondary">{{ __('cabinet.empty_payments') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

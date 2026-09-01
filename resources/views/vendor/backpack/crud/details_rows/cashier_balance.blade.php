@php
    $hasDrift = abs($calculatedClosing - $storedClosing) >= 0.01;
@endphp

<div class="cashier-details p-3" bp-section="crud-details-row">

    {{-- How the day was calculated: opening + cash in − cash out = closing --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('cashier.details.opening_balance') }}</div>
                    <div class="h3 mb-0">{{ number_format($openingBalance, 2) }} ₾</div>
                    <div class="text-secondary small">{{ __('cashier.details.opening_hint') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('cashier.details.cash_in') }}</div>
                    <div class="h3 mb-0 text-success">+ {{ number_format($cashIn, 2) }} ₾</div>
                    <div class="text-secondary small">{{ __('cashier.details.cash_in_hint', ['count' => $payments->count()]) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('cashier.details.cash_out') }}</div>
                    <div class="h3 mb-0 text-danger">− {{ number_format($cashOut, 2) }} ₾</div>
                    <div class="text-secondary small">{{ __('cashier.details.cash_out_hint', ['count' => $expenses->count()]) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('cashier.details.closing_balance') }}</div>
                    <div class="h3 mb-0 {{ $storedClosing >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($storedClosing, 2) }} ₾
                    </div>
                    <div class="text-secondary small">
                        {{ __('cashier.details.net_change') }} {{ $netChange >= 0 ? '+' : '−' }}{{ number_format(abs($netChange), 2) }} ₾
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payments/expenses edited after the snapshot make the stored amount stale --}}
    @if ($hasDrift)
        <div class="alert alert-warning py-2 mb-3">
            <strong>{{ __('cashier.details.drift_title') }}</strong>
            {{ __('cashier.details.drift_body') }}
            <strong>{{ number_format($calculatedClosing, 2) }} ₾</strong>
            ({{ number_format($openingBalance, 2) }} + {{ number_format($cashIn, 2) }} − {{ number_format($cashOut, 2) }}),
            {{ __('cashier.details.drift_stored') }} <strong>{{ number_format($storedClosing, 2) }} ₾</strong>.
            {{ __('cashier.details.drift_reason') }}
        </div>
    @endif

    <div class="row g-3">

        {{-- CASH IN: payments --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <h4 class="card-title mb-0">
                        {{ __('cashier.details.payments') }}
                        <span class="badge bg-secondary-lt ms-1">{{ $payments->count() }}</span>
                    </h4>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/payment') }}"
                       class="btn btn-sm btn-outline-secondary">
                        {{ __('cashier.details.view_all') }}
                    </a>
                </div>

                @if ($payments->isEmpty())
                    <div class="card-body text-secondary text-center py-4">
                        {{ __('cashier.details.no_payments') }}
                    </div>
                @else
                    <div class="table-responsive cashier-subtable">
                        <table class="table table-sm table-vcenter card-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('cashier.details.time') }}</th>
                                    <th>{{ __('cashier.details.client') }}</th>
                                    <th>{{ __('cashier.details.order') }}</th>
                                    <th>{{ __('cashier.details.type') }}</th>
                                    <th class="text-end">{{ __('cashier.details.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td class="text-nowrap">
                                            {{ optional($payment->payment_date)->format('H:i') ?? '—' }}
                                        </td>
                                        <td>
                                            @if ($payment->client)
                                                {{ $payment->client->name }}
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($payment->order_id)
                                                <a href="{{ route('order.edit', $payment->order_id) }}">#{{ $payment->order_id }}</a>
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                        <td>{{ \App\Models\Payment::types()[$payment->type] ?? ($payment->type ?? '—') }}</td>
                                        <td class="text-end text-nowrap text-success">
                                            {{ number_format((float) $payment->amount_gel, 2) }} ₾
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4">{{ __('cashier.details.total_cash_in') }}</th>
                                    <th class="text-end text-nowrap text-success">
                                        {{ number_format($cashIn, 2) }} ₾
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- CASH OUT: expenses --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <h4 class="card-title mb-0">
                        {{ __('cashier.details.expenses') }}
                        <span class="badge bg-secondary-lt ms-1">{{ $expenses->count() }}</span>
                    </h4>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/cashier-expense') }}"
                       class="btn btn-sm btn-outline-secondary">
                        {{ __('cashier.details.view_all') }}
                    </a>
                </div>

                @if ($expenses->isEmpty())
                    <div class="card-body text-secondary text-center py-4">
                        {{ __('cashier.details.no_expenses') }}
                    </div>
                @else
                    <div class="table-responsive cashier-subtable">
                        <table class="table table-sm table-vcenter card-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('cashier.details.time') }}</th>
                                    <th>{{ __('cashier.details.category') }}</th>
                                    <th>{{ __('cashier.details.description') }}</th>
                                    <th class="text-end">{{ __('cashier.details.amount') }}</th>
                                    <th class="text-end">{{ __('cashier.details.credit') }}</th>
                                    <th class="text-end">{{ __('cashier.details.paid') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expenses as $expense)
                                    <tr>
                                        <td class="text-nowrap">
                                            {{ optional($expense->expense_date)->format('H:i') ?? '—' }}
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-lt">
                                                {{ $expense->category?->name ?? '—' }}
                                            </span>
                                        </td>
                                        <td>{{ $expense->description ?: '—' }}</td>
                                        <td class="text-end text-nowrap">
                                            {{ number_format((float) $expense->purchase_amount, 2) }} ₾
                                        </td>
                                        <td class="text-end text-nowrap">
                                            {{ number_format((float) $expense->credit, 2) }} ₾
                                        </td>
                                        <td class="text-end text-nowrap text-danger">
                                            {{ number_format((float) $expense->paid_amount, 2) }} ₾
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5">{{ __('cashier.details.total_cash_out') }}</th>
                                    <th class="text-end text-nowrap text-danger">
                                        {{ number_format($cashOut, 2) }} ₾
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

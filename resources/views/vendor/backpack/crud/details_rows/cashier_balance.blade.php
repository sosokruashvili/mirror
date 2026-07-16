@php
    $hasDrift = abs($calculatedClosing - $storedClosing) >= 0.01;
@endphp

<div class="cashier-details p-3" bp-section="crud-details-row">

    {{-- How the day was calculated: opening + cash in − cash out = closing --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Opening Balance</div>
                    <div class="h3 mb-0">{{ number_format($openingBalance, 2) }} ₾</div>
                    <div class="text-secondary small">previous day's closing</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Cash In</div>
                    <div class="h3 mb-0 text-success">+ {{ number_format($cashIn, 2) }} ₾</div>
                    <div class="text-secondary small">{{ $payments->count() }} cash payment(s)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Cash Out</div>
                    <div class="h3 mb-0 text-danger">− {{ number_format($cashOut, 2) }} ₾</div>
                    <div class="text-secondary small">{{ $expenses->count() }} cash expense(s)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Closing Balance</div>
                    <div class="h3 mb-0 {{ $storedClosing >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($storedClosing, 2) }} ₾
                    </div>
                    <div class="text-secondary small">
                        net change: {{ $netChange >= 0 ? '+' : '−' }}{{ number_format(abs($netChange), 2) }} ₾
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payments/expenses edited after the snapshot make the stored amount stale --}}
    @if ($hasDrift)
        <div class="alert alert-warning py-2 mb-3">
            <strong>Snapshot out of date:</strong>
            recalculating from current data gives
            <strong>{{ number_format($calculatedClosing, 2) }} ₾</strong>
            ({{ number_format($openingBalance, 2) }} + {{ number_format($cashIn, 2) }} − {{ number_format($cashOut, 2) }}),
            but the stored snapshot is <strong>{{ number_format($storedClosing, 2) }} ₾</strong>.
            Payments or expenses were likely changed after the snapshot was taken.
        </div>
    @endif

    <div class="row g-3">

        {{-- CASH IN: payments --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <h4 class="card-title mb-0">
                        Cash Payments
                        <span class="badge bg-secondary-lt ms-1">{{ $payments->count() }}</span>
                    </h4>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/payment') }}"
                       class="btn btn-sm btn-outline-secondary">
                        View all
                    </a>
                </div>

                @if ($payments->isEmpty())
                    <div class="card-body text-secondary text-center py-4">
                        No cash payments on this day.
                    </div>
                @else
                    <div class="table-responsive cashier-subtable">
                        <table class="table table-sm table-vcenter card-table mb-0">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Client</th>
                                    <th>Order</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
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
                                    <th colspan="4">Total cash in</th>
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
                        Cash Expenses
                        <span class="badge bg-secondary-lt ms-1">{{ $expenses->count() }}</span>
                    </h4>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/cashier-expense') }}"
                       class="btn btn-sm btn-outline-secondary">
                        View all
                    </a>
                </div>

                @if ($expenses->isEmpty())
                    <div class="card-body text-secondary text-center py-4">
                        No cash expenses on this day.
                    </div>
                @else
                    <div class="table-responsive cashier-subtable">
                        <table class="table table-sm table-vcenter card-table mb-0">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
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
                                                {{ \App\Models\CashierExpense::categories()[$expense->category] ?? ($expense->category ?? '—') }}
                                            </span>
                                        </td>
                                        <td>{{ $expense->description ?: '—' }}</td>
                                        <td class="text-end text-nowrap text-danger">
                                            {{ number_format((float) $expense->amount_gel, 2) }} ₾
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total cash out</th>
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

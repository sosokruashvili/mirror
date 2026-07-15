@php
    $countedOrdersTotal = collect($countedOrderIds)->sum(fn ($id) => $orderTotals[$id] ?? 0);

    $paymentStatusClass = function ($status) {
        return match ($status) {
            'Paid' => 'bg-success-lt',
            'Pending' => 'bg-warning-lt',
            default => 'bg-secondary-lt',
        };
    };

    $orderStatusClass = function ($status) {
        return match ($status) {
            'draft' => 'bg-secondary-lt',
            'new' => 'bg-azure-lt',
            'working' => 'bg-warning-lt',
            'ready', 'done' => 'bg-teal-lt',
            'finished' => 'bg-success-lt',
            default => 'bg-secondary-lt',
        };
    };
@endphp

<div class="client-balance-details p-3" bp-section="crud-details-row">

    {{-- Balance recap for this client, mirroring the columns of the row above --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Starting Balance</div>
                    <div class="h3 mb-0">{{ number_format($components['starting_balance'], 0) }} ₾</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Payments Total</div>
                    <div class="h3 mb-0 text-success">{{ number_format($components['payments_total'], 0) }} ₾</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Orders Total</div>
                    <div class="h3 mb-0 text-info">{{ number_format($components['orders_total'], 0) }} ₾</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">Balance</div>
                    <div class="h3 mb-0 {{ $components['balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($components['balance'], 0) }} ₾
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        {{-- PAYMENTS --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <h4 class="card-title mb-0">
                        Payments
                        <span class="badge bg-secondary-lt ms-1">{{ $payments->count() }}</span>
                    </h4>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/payment') }}?client_id={{ $entry->id }}"
                       class="btn btn-sm btn-outline-secondary">
                        View all
                    </a>
                </div>

                @if ($payments->isEmpty())
                    <div class="card-body text-secondary text-center py-4">
                        No payments for this client yet.
                    </div>
                @else
                    <div class="table-responsive client-balance-subtable">
                        <table class="table table-sm table-vcenter card-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    @php $counted = $payment->status === 'Paid'; @endphp
                                    <tr @class(['text-secondary' => !$counted])>
                                        <td class="text-nowrap">
                                            {{ optional($payment->payment_date)->format('d M Y') ?? '—' }}
                                        </td>
                                        <td>{{ $payment->method ?? '—' }}</td>
                                        <td>{{ \App\Models\Payment::types()[$payment->type] ?? ($payment->type ?? '—') }}</td>
                                        <td>
                                            @if ($payment->order_id)
                                                <a href="{{ route('order.edit', $payment->order_id) }}">#{{ $payment->order_id }}</a>
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $paymentStatusClass($payment->status) }}">
                                                {{ $payment->status ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap {{ $counted ? 'text-success' : '' }}">
                                            {{ number_format((float) $payment->amount_gel, 2) }} ₾
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5">
                                        Counted in balance
                                        <span class="text-secondary fw-normal">({{ $countedPaymentsCount }} paid)</span>
                                    </th>
                                    <th class="text-end text-nowrap text-success">
                                        {{ number_format($countedPaymentsTotal, 2) }} ₾
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ORDERS --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <h4 class="card-title mb-0">
                        Orders
                        <span class="badge bg-secondary-lt ms-1">{{ $orders->count() }}</span>
                    </h4>
                    <a href="{{ url(config('backpack.base.route_prefix') . '/order') }}?client_id={{ $entry->id }}"
                       class="btn btn-sm btn-outline-secondary">
                        View all
                    </a>
                </div>

                @if ($orders->isEmpty())
                    <div class="card-body text-secondary text-center py-4">
                        No orders for this client yet.
                    </div>
                @else
                    <div class="table-responsive client-balance-subtable">
                        <table class="table table-sm table-vcenter card-table mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Paid</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    @php $counted = in_array($order->id, $countedOrderIds, true); @endphp
                                    <tr @class(['text-secondary' => !$counted])>
                                        <td class="text-nowrap">
                                            <a href="{{ route('order.edit', $order->id) }}">#{{ $order->id }}</a>
                                        </td>
                                        <td class="text-nowrap">
                                            {{ optional($order->created_at)->format('d M Y') ?? '—' }}
                                        </td>
                                        <td>{{ $order->order_type ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $orderStatusClass($order->status) }}">
                                                {{ ucfirst($order->status ?? '—') }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($order->paid)
                                                <span class="badge bg-success-lt">Paid</span>
                                            @else
                                                <span class="badge bg-danger-lt">Unpaid</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap {{ $counted ? 'text-info' : '' }}">
                                            {{ number_format($orderTotals[$order->id] ?? 0, 2) }} ₾
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5">
                                        Counted in balance
                                        <span class="text-secondary fw-normal">({{ count($countedOrderIds) }} non-draft)</span>
                                    </th>
                                    <th class="text-end text-nowrap text-info">
                                        {{ number_format($countedOrdersTotal, 2) }} ₾
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

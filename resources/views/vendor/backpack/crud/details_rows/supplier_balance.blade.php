@php
    $typeClass = function ($type) {
        return match ($type) {
            \App\Models\CashierExpense::TYPE_CASH => 'bg-success-lt',
            \App\Models\CashierExpense::TYPE_TRANSFER => 'bg-info-lt',
            \App\Models\CashierExpense::TYPE_PM_TRANSFER => 'bg-purple-lt',
            default => 'bg-secondary-lt',
        };
    };
@endphp

<div class="supplier-balance-details p-3" bp-section="crud-details-row">

    {{-- Balance recap for this supplier, mirroring the columns of the row above --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('supplier_balance.details.expenses_total') }}</div>
                    <div class="h3 mb-0">{{ number_format($expensesTotal, 2) }} ₾</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('supplier_balance.details.paid_total') }}</div>
                    <div class="h3 mb-0 text-success">{{ number_format($paidTotal, 2) }} ₾</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('supplier_balance.details.credit_total') }}</div>
                    <div class="h3 mb-0 {{ $creditTotal > 0 ? 'text-danger' : 'text-secondary' }}">
                        {{ number_format($creditTotal, 2) }} ₾
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="text-secondary small">{{ __('supplier_balance.details.balance') }}</div>
                    <div class="h3 mb-0 {{ $balance < 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($balance, 2) }} ₾
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- EXPENSES --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <h4 class="card-title mb-0">
                {{ __('supplier_balance.details.expenses') }}
                <span class="badge bg-secondary-lt ms-1">{{ $expenses->count() }}</span>
            </h4>
            <a href="{{ url(config('backpack.base.route_prefix') . '/cashier-expense') }}?supplier_id={{ $entry->id }}"
               class="btn btn-sm btn-outline-secondary">
                {{ __('supplier_balance.details.view_all') }}
            </a>
        </div>

        @if ($expenses->isEmpty())
            <div class="card-body text-secondary text-center py-4">
                {{ __('supplier_balance.details.no_expenses') }}
            </div>
        @else
            <div class="table-responsive supplier-balance-subtable">
                <table class="table table-sm table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('supplier_balance.details.date') }}</th>
                            <th>{{ __('supplier_balance.details.type') }}</th>
                            <th>{{ __('supplier_balance.details.category') }}</th>
                            <th>{{ __('supplier_balance.details.product') }}</th>
                            <th>{{ __('supplier_balance.details.description') }}</th>
                            <th class="text-end">{{ __('supplier_balance.details.amount') }}</th>
                            <th class="text-end">{{ __('supplier_balance.details.paid') }}</th>
                            <th class="text-end">{{ __('supplier_balance.details.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td class="text-nowrap">
                                    {{ optional($expense->expense_date)->format('d M Y') ?? '—' }}
                                </td>
                                <td>
                                    <span class="badge {{ $typeClass($expense->type) }}">
                                        {{ $expense->type ?? '—' }}
                                    </span>
                                </td>
                                <td>{{ $expense->category->name ?? '—' }}</td>
                                <td>{{ $expense->product->title ?? '—' }}</td>
                                <td class="text-secondary">{{ \Illuminate\Support\Str::limit($expense->description, 60) ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    {{ number_format((float) $expense->amount_gel, 2) }} ₾
                                </td>
                                <td class="text-end text-nowrap text-success">
                                    {{ number_format($expense->paid_amount, 2) }} ₾
                                </td>
                                <td class="text-end text-nowrap {{ (float) $expense->credit > 0 ? 'text-danger' : 'text-secondary' }}">
                                    {{ number_format((float) $expense->credit, 2) }} ₾
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5">{{ __('supplier_balance.details.totals') }}</th>
                            <th class="text-end text-nowrap">{{ number_format($expensesTotal, 2) }} ₾</th>
                            <th class="text-end text-nowrap text-success">{{ number_format($paidTotal, 2) }} ₾</th>
                            <th class="text-end text-nowrap {{ $creditTotal > 0 ? 'text-danger' : '' }}">{{ number_format($creditTotal, 2) }} ₾</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

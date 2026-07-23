@php
    $rows = $widget['rows'] ?? collect();
    $products = $widget['products'] ?? collect();
    $selectedProduct = $widget['selected_product'] ?? null;
    $availableDates = $widget['available_dates'] ?? collect();
    $selectedDate = $widget['selected_date'] ?? null;
    $isLive = $widget['is_live'] ?? false;

    // Keep the CRUD list's own query params (its filters, sorting) intact when this
    // table's own controls submit, so they don't disturb the table below.
    $carryOver = collect(request()->except(['summary_product_id', 'snapshot_date']))
        ->filter(fn ($value) => is_scalar($value));

    // Export preserves this table's own date + product filter.
    $exportQuery = array_filter([
        'snapshot_date' => $selectedDate,
        'summary_product_id' => $selectedProduct,
    ]);
@endphp

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h4 class="mb-0">Remaining in warehouse (m²)</h4>
            <small class="text-muted">
                Per product: total warehouse area minus total order expenses.
                @if($isLive)
                    <span class="badge bg-warning text-dark">Live — no daily snapshot yet</span>
                @elseif($selectedDate)
                    Daily snapshot of {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                @endif
            </small>

            <form method="POST" action="{{ route('warehouse.recalculate') }}" class="mb-0 mt-2">
                @csrf
                @if($selectedProduct)
                    <input type="hidden" name="summary_product_id" value="{{ $selectedProduct }}">
                @endif
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="la la-sync"></i> Recalculate now
                </button>
            </form>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="d-flex flex-wrap align-items-center gap-2 mb-0">
            <a href="{{ route('warehouse.exportRemainingStock', $exportQuery) }}"
               class="btn btn-sm btn-secondary">
                <i class="la la-download"></i> Export to Excel
            </a>

            @foreach($carryOver as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <label for="snapshot_date" class="form-label mb-0 text-muted small">Date</label>
            <select name="snapshot_date"
                    id="snapshot_date"
                    class="form-select form-select-sm"
                    style="min-width: 160px;"
                    onchange="this.form.submit()"
                    @disabled($availableDates->isEmpty())>
                @if($availableDates->isEmpty())
                    <option value="">No snapshots yet</option>
                @else
                    @foreach($availableDates as $date)
                        <option value="{{ $date }}" @selected((string) $selectedDate === (string) $date)>
                            {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                        </option>
                    @endforeach
                @endif
            </select>

            <label for="summary_product_id" class="form-label mb-0 text-muted small">Product</label>
            <select name="summary_product_id"
                    id="summary_product_id"
                    class="form-select form-select-sm"
                    style="min-width: 220px;"
                    onchange="this.form.submit()">
                <option value="">All products</option>
                @foreach($products as $id => $title)
                    <option value="{{ $id }}" @selected((string) $selectedProduct === (string) $id)>{{ $title }}</option>
                @endforeach
            </select>

            @if($selectedProduct)
                <a href="{{ url()->current() . '?' . http_build_query(array_merge($carryOver->all(), array_filter(['snapshot_date' => $selectedDate]))) }}"
                   class="btn btn-sm btn-link text-muted">Reset</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Offcut (%)</th>
                        <th class="text-end">Offcut (m²)</th>
                        <th class="text-end">In warehouse (m²)</th>
                        <th class="text-end">Expenses (m²)</th>
                        <th class="text-end">Remaining (m²)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->title }}</td>
                            <td class="text-end">
                                @if(($row->offcut ?? 0) > 0)
                                    {{ number_format($row->offcut, 2) }} %
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(($row->offcut_area ?? 0) > 0)
                                    {{ number_format($row->offcut_area, 3) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($row->warehouse_area, 3) }}</td>
                            <td class="text-end">{{ number_format($row->expenses, 3) }}</td>
                            <td class="text-end fw-bold {{ $row->remaining < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($row->remaining, 3) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

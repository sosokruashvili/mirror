@php
    $rows = $widget['rows'] ?? collect();
    $products = $widget['products'] ?? collect();
    $selectedProduct = $widget['selected_product'] ?? null;

    // Keep the CRUD list's own query params (its filters, sorting) intact when this
    // table's filter submits, so filtering here doesn't disturb the table below.
    $carryOver = collect(request()->except(['summary_product_id']))
        ->filter(fn ($value) => is_scalar($value));
@endphp

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-0">Remaining in warehouse (m²)</h4>
            <small class="text-muted">Per product: total warehouse area minus total order expenses</small>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2 mb-0">
            @foreach($carryOver as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

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
                <a href="{{ url()->current() . ($carryOver->isNotEmpty() ? '?' . http_build_query($carryOver->all()) : '') }}"
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
                        <th class="text-end">In warehouse (m²)</th>
                        <th class="text-end">Expenses (m²)</th>
                        <th class="text-end">Remaining (m²)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->title }}</td>
                            <td class="text-end">{{ number_format($row->warehouse_area, 3) }}</td>
                            <td class="text-end">{{ number_format($row->expenses, 3) }}</td>
                            <td class="text-end fw-bold {{ $row->remaining < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($row->remaining, 3) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

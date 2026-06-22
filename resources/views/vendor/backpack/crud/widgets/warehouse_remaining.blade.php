@php
    $rows = $widget['rows'] ?? collect();
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h4 class="mb-0">Remaining in warehouse (m²)</h4>
        <small class="text-muted">Per product: total warehouse area minus total order expenses</small>
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

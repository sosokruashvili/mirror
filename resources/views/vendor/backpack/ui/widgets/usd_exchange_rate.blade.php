{{-- USD Exchange Rate Widget — kept for optional re-enable on the dashboard --}}
<div class="col-sm-6 col-md-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="subheader">USD Exchange Rate</div>
                <div class="ms-auto">
                    <i class="la la-dollar-sign text-success" style="font-size: 2rem;"></i>
                </div>
            </div>
            @php
                $currency = \App\Models\Currency::latest()->first();
            @endphp
            @if($currency)
                <div class="h1 mb-3 text-success">{{ number_format($currency->rate_usd, 4) }} ₾</div>
                <div class="d-flex mb-2">
                    <div class="text-muted">
                        <small>Last updated: {{ $currency->created_at->diffForHumans() }}</small>
                    </div>
                </div>
                <div class="text-muted">
                    <small>{{ $currency->created_at->format('d M Y, H:i') }}</small>
                </div>
            @else
                <div class="h1 mb-3 text-muted">N/A</div>
                <div class="d-flex mb-2">
                    <div class="text-muted">
                        <small>No exchange rate data available</small>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@extends(backpack_view('blank'))

@section('content')
    <div class="row">
        {{-- Clients Count Widget --}}
        <div class="col-sm-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Total Clients</div>
                    </div>
                    <div class="h1 mb-3">{{ \App\Models\Client::count() }}</div>
                    <div class="d-flex mb-2">
                        <div>All registered clients</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Orders Count Widget --}}
        <div class="col-sm-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Total Orders</div>
                    </div>
                    <div class="h1 mb-3">{{ \App\Models\Order::count() }}</div>
                    <div class="d-flex mb-2">
                        <div>All orders in system</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Products Count Widget --}}
        <div class="col-sm-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Total Products</div>
                    </div>
                    <div class="h1 mb-3">{{ \App\Models\Product::count() }}</div>
                    <div class="d-flex mb-2">
                        <div>Available products</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pieces Count Widget --}}
        <div class="col-sm-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Total Pieces</div>
                    </div>
                    <div class="h1 mb-3">{{ \App\Models\Piece::count() }}</div>
                    <div class="d-flex mb-2">
                        <div>All pieces in production</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Second Row --}}
    <div class="row mt-4">
        {{-- USD Exchange Rate Widget --}}
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
    </div>
@endsection

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
                    <div class="h1 mb-3">{{ \App\Models\Order::where('status', '!=', 'draft')->count() }}</div>
                    <div class="d-flex mb-2">
                        <div>Confirmed orders (excluding drafts)</div>
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
                    <div class="h1 mb-3">{{ \App\Models\Piece::whereHas('order', fn($q) => $q->where('status', '!=', 'draft'))->count() }}</div>
                    <div class="d-flex mb-2">
                        <div>Production pieces (excluding drafts)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daily Orders & Income Chart Widget --}}
    <div class="row mt-4">
        @include('vendor.backpack.ui.widgets.daily_stats_chart')
    </div>

    {{-- Orders by Product Type & Orders Area Chart Widgets --}}
    <div class="row mt-4">
        @include('vendor.backpack.ui.widgets.product_type_stats_chart')
        @include('vendor.backpack.ui.widgets.orders_area_chart')
    </div>

    {{-- Product Type Pie Chart Widget --}}
    <div class="row mt-4">
        @include('vendor.backpack.ui.widgets.product_type_pie_chart')
    </div>

    {{-- USD Exchange Rate (disabled; uncomment to restore)
    <div class="row mt-4">
        @include('vendor.backpack.ui.widgets.usd_exchange_rate')
    </div>
    --}}
@endsection

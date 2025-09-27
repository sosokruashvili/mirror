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
@endsection

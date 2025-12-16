@extends(backpack_view('blank'))

@php
    // Define status badge colors
    $statusColors = [
        'draft' => 'bg-secondary',
        'new' => 'bg-primary',
        'pending' => 'bg-warning',
        'working' => 'bg-info',
        'done' => 'bg-success',
        'finished' => 'bg-success',
    ];
    
    // Build widget content for each status that exists in database
    $statusWidgets = [];
    foreach ($statusCountsFormatted as $status => $data) {
        $color = $statusColors[$status] ?? 'bg-secondary';
        $statusWidgets[] = [
            'type' => 'progress',
            'class' => 'card text-white ' . $color . ' mb-0',
            'value' => $data['count'],
            'description' => $data['label'] . ': ' . $data['count'],
            'wrapper' => ['class' => 'col-lg-2 col-md-3 col-sm-4 col-6 mb-3'],
        ];
    }
    
    $widgets['before_content'][] = [
        'type' => 'div',
        'class' => 'row',
        'content' => $statusWidgets,
    ];
@endphp

@section('content')
@php
    $isTeamUser = backpack_user() && backpack_user()->hasRole('team');
@endphp
<style>
    /* Reduce widget height */
    .widget-progress .card-body {
        padding: 0.75rem 1rem !important;
    }
    
    .widget-progress .text-value {
        font-size: 1.5rem !important;
        line-height: 1.2 !important;
        margin-bottom: 0.25rem !important;
    }
    
    .widget-progress .card-body > div:not(.text-value) {
        font-size: 0.875rem !important;
        line-height: 1.2 !important;
    }
    
    .widget-progress .card {
        min-height: auto !important;
    }
    /* Hide sidebar for team users */
    @if($isTeamUser)
        .navbar-vertical,
        .navbar-brand,
        .navbar-nav,
        aside,
        .sidebar,
        .navbar {
            display: none !important;
        }
        
        .page-wrapper,
        .page {
            margin-left: 0 !important;
            padding-left: 0 !important;
        }
        
        .page-body {
            margin-left: 0 !important;
        }
    @endif
    
    /* Optimized for 10-inch Android tablet */
    body {
        font-size: 16px;
    }
    
    .order-tile {
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }
    
    .order-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .order-header {
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    
    .order-id {
        font-size: 18px;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 4px;
        line-height: 1.2;
    }
    
    .order-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .order-details {
        flex-grow: 1;
        margin-bottom: 10px;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid #f5f5f5;
        font-size: 13px;
        line-height: 1.3;
    }
    
    .detail-label {
        font-weight: 600;
        color: #666;
    }
    
    .detail-value {
        color: #2c3e50;
        text-align: right;
    }
    
    .order-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }
    
    .order-actions .btn {
        padding: 4px 12px;
        font-size: 13px;
    }
    

    
    /* Ensure Bootstrap button colors are applied */
    .order-actions .btn-primary {
        background-color:rgb(96, 129, 179);
        border-color: #0d6efd;
        color: #fff;
    }
    
    .order-actions .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
        color: #fff;
    }
    
    .order-actions .btn-success {
        background-color: #198754;
        border-color: #198754;
        color: #fff;
    }
    
    .order-actions .btn-success:hover {
        background-color: #157347;
        border-color: #146c43;
        color: #fff;
    }
    
    .orders-grid {
        padding: 20px 0;
    }
    
    .orders-grid .order-tile {
        margin-bottom: 20px;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Order Processing</h1>
                <form method="POST" action="{{ route('backpack.auth.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="la la-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
            
            {{-- Hidden CSRF token for AJAX requests --}}
            <input type="hidden" name="_token" id="csrf-token" value="{{ csrf_token() }}">
            
            <div class="orders-grid">
                <div class="row">
                @forelse($orders as $order)
                    <div class="col-md-3 col-sm-6 col-12" style="margin-bottom: 10px;">
                        <div class="order-tile">
                        <div class="order-header">
                            <div class="order-id">Order #{{ $order->id }}</div>
                            {!! status_badge($order->status) !!}
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-row">
                                <span class="detail-label">Client:</span>
                                <span class="detail-value">{{ $order->client->name ?? 'N/A' }}</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Type:</span>
                                <span class="detail-value">{{ order_type_ge($order->order_type ?? '') }}</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Product:</span>
                                <span class="detail-value">{{ product_type_ge($order->product_type ?? '') }}</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-value">{{ number_format($order->price_gel ?? $order->calculateTotalPrice(), 2) }} ₾</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Created:</span>
                                <span class="detail-value">{{ $order->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                        
                        <div class="order-actions">
                            <button class="btn btn-primary" onclick="previewOrder('{{ url(config("backpack.base.route_prefix") . "/order/" . $order->id . "/show") }}')">
                                View
                            </button>
                            <button class="btn btn-success" onclick="finishOrder({{ $order->id }})">
                                Finish
                            </button>
                        </div>
                    </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h4>No orders found</h4>
                            <p>There are no orders to process at this time.</p>
                        </div>
                    </div>
                @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
@php
    // Get and process Backpack alerts for display
    $backpack_alerts = \Prologue\Alerts\Facades\Alert::getMessages();
    \Prologue\Alerts\Facades\Alert::flush();
@endphp

@if(!empty($backpack_alerts))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @foreach($backpack_alerts as $type => $messages)
            @foreach($messages as $message)
                new Noty({
                    type: "{{ $type }}",
                    text: "{!! addslashes($message) !!}",
                    timeout: 3000
                }).show();
            @endforeach
        @endforeach
    });
</script>
@endif

<script>
    function previewOrder(url) {
        // Open order preview page in new tab
        window.open(url, '_blank');
    }
    
    function finishOrder(orderId) {
        // Confirm action
        if (!confirm('Are you sure you want to mark this order as finished?')) {
            return;
        }
        
        // Disable the button to prevent double-clicks
        const button = event.target;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Processing...';
        
        // Get CSRF token
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value;
        
        // Use form submission to handle redirect properly
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("team.orders.finish", ":id") }}'.replace(':id', orderId);
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = token;
        form.appendChild(csrfInput);
        
        // Submit form - this will handle the redirect and flash message properly
        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush
@endsection


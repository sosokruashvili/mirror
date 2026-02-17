@extends(backpack_view('blank'))

@section('content')
@php
    $isTeamUser = backpack_user() && backpack_user()->hasRole('team');
@endphp
<style>

    body {
        background-color:rgb(54, 54, 54);
    }

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
    
    .order-header {
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 8px;
        margin-bottom: 10px;
        margin-top: -10px;
    }
    
    .order-id {
        font-size: 18px;
        font-weight: bold;
        color: #2c3e50;
        vertical-align: middle;
        display: flex;
        align-items: center;
        margin-left: 0px;
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
    
    .created-at {
        font-size: 16px;
        color: #333;
        text-align: right;
        margin-right: 0px;
        font-weight: bold;
        vertical-align: middle;
        display: flex;
        align-items: center;
        justify-content: flex-end;
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
    
    .size-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
        margin-bottom: 8px;
    }
    
    .size-tag {
        display: inline-block;
        background-color: #6081b3;
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .service-shortname-tag {
        display: inline-block;
        background-color: #4a6fa5;
        color: #fff;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        margin-left: 4px;
        white-space: nowrap;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .size-tags-empty {
        color: #999;
        font-size: 11px;
        font-style: italic;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="orders-grid">
                <div class="row">
                @forelse($orders as $order)
                    <div class="col-md-3 col-sm-6 col-12" style="margin-bottom: 10px;">
                        <div class="order-tile">
                            <div class="order-header">
                                <div class="row">
                                    <div class="col-md-3 order-id">#{{ $order->id }}</div>
                                    <div class="col-md-3 order-status">{!! status_badge($order->status) !!}</div>
                                    <div class="col-md-6 created-at">{{ $order->created_at->format('Y-m-d H:i') }}</div>
                                </div>
                                
                            </div>
                            
                            <div class="order-details">
                                <div class="detail-row">
                                    <span class="detail-label">კლიენტი:</span>
                                    <span class="detail-value">{{ $order->client->name ?? 'N/A' }}</span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">ფასი:</span>
                                    <span class="detail-value">{{ number_format($order->price_gel ?? $order->calculateTotalPrice(), 2) }} ₾</span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">პროდუქცია:</span>
                                    <span class="detail-value">{{ product_type_ge($order->product_type ?? '') }}</span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">მასალა:</span>
                                    <span class="detail-value">{{ $order->products->pluck('title')->implode(' x ') }}</span>
                                </div>
                                
                                @php
                                    // Get unique piece sizes with quantities and service shortnames for this order
                                    $piecesWithSizes = $order->pieces->filter(function($piece) {
                                        return $piece->width && $piece->height;
                                    });
                                    
                                    $sizeGroups = [];
                                    foreach($piecesWithSizes as $piece) {
                                        $key = number_format($piece->width, 0) . 'x' . number_format($piece->height, 0);
                                        if (!isset($sizeGroups[$key])) {
                                            $sizeGroups[$key] = [
                                                'width' => number_format($piece->width, 0),
                                                'height' => number_format($piece->height, 0),
                                                'quantity' => 0,
                                                'piece_ids' => [],
                                                'service_shortnames' => []
                                            ];
                                        }
                                        $sizeGroups[$key]['quantity'] += $piece->quantity ?? 1;
                                        $sizeGroups[$key]['piece_ids'][] = $piece->id;
                                        
                                        // Get services associated with this piece
                                        $pieceServices = $order->services->filter(function($service) use ($piece) {
                                            return $service->pivot->piece_id == $piece->id;
                                        });
                                        
                                        // Collect unique service shortnames for this size
                                        foreach($pieceServices as $service) {
                                            if ($service->shortname && !in_array($service->shortname, $sizeGroups[$key]['service_shortnames'])) {
                                                $sizeGroups[$key]['service_shortnames'][] = $service->shortname;
                                            }
                                        }
                                    }
                                    $uniqueSizes = array_values($sizeGroups);
                                @endphp
                                
                                @if(count($uniqueSizes) > 0)
                                    <div class="size-tags">
                                        @foreach($uniqueSizes as $size)
                                            <span class="size-tag">
                                                {{ $size['width'] }} × {{ $size['height'] }} cm (×{{ $size['quantity'] }})
                                                @if(count($size['service_shortnames']) > 0)
                                                    @foreach($size['service_shortnames'] as $shortname)
                                                        <span class="service-shortname-tag">{{ $shortname }}</span>
                                                    @endforeach
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="size-tags-empty">No sizes available</div>
                                @endif
                            </div>
                            
                            <div class="order-actions">
                                <button class="btn btn-primary" onclick="previewOrder('{{ url(config("backpack.base.route_prefix") . "/order/" . $order->id . "/show") }}')">
                                    ნახვა
                                </button>
                                <button class="btn btn-success" onclick="finishOrder({{ $order->id }})">
                                    დასრულება
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


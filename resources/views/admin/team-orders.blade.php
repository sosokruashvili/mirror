@extends(backpack_view('blank'))

@section('content')
@php
    $isTeamUser = backpack_user() && backpack_user()->hasRole('team');
    $dragStorageKey = 'teamOrdersGridOrder:v1:' . (backpack_user()->id ?? 'guest');
    $showArchived = $showArchived ?? false;
    $dateFrom = $dateFrom ?? request()->query('from');
    $dateTo = $dateTo ?? request()->query('to');
    $status = $status ?? 'all';
    $statusLabels = $statusLabels ?? [];

    $toggleQuery = array_filter([
        'from' => $dateFrom ?: null,
        'to' => $dateTo ?: null,
        // Preserve status when it's set and isn't "all"
        'status' => ($status !== null && $status !== '' && $status !== 'all') ? $status : null,
    ]);
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

    .order-card .order-tile {
        cursor: grab;
        user-select: none;
        -webkit-user-select: none;
        -webkit-user-drag: none;
    }
    .order-card.dragging .order-tile {
        cursor: grabbing;
        box-shadow: 0 10px 24px rgba(0,0,0,0.25);
    }
    .order-card-placeholder {
        border: 3px dashed rgba(96, 129, 179, 0.9);
        border-radius: 8px;
        margin-bottom: 10px;
        background: rgba(255,255,255,0.06);
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
    .order-actions .btn-archive {
        margin-left: auto;
    }
    
    .order-actions .btn {
        padding: 4px 12px;
        font-size: 13px;
    }
    
    .created-at {
        font-size: 14px;
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
    <div class="d-flex flex-wrap align-items-center justify-content-between pt-3 gap-2">
        <form method="GET" action="{{ route('team.orders') }}" class="d-flex flex-wrap align-items-end gap-2" autocomplete="off">
            @if($showArchived)
                <input type="hidden" name="view" value="archived" />
            @endif

            <div>
                <label class="form-label mb-1 text-light">From</label>
                <input type="date" class="form-control" name="from" value="{{ $dateFrom }}" />
            </div>

            <div>
                <label class="form-label mb-1 text-light">To</label>
                <input type="date" class="form-control" name="to" value="{{ $dateTo }}" />
            </div>

            <div style="min-width: 180px;">
                <label class="form-label mb-1 text-light">Status</label>
                <select class="form-select" name="status" id="statusFilter" autocomplete="off" data-initial-status="{{ $status ?? 'all' }}">
                    <option value="all" {{ ($status === 'all' || $status === null || $status === '') ? 'selected' : '' }}>All</option>
                    @foreach($statusLabels as $key => $label)
                        @continue(in_array($key, ['draft','ready','finished'], true))
                        <option value="{{ $key }}" {{ ($status === $key) ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ $showArchived ? route('team.orders', ['view' => 'archived']) : route('team.orders') }}" class="btn btn-outline-light">Reset</a>
        </form>

        @if($showArchived)
            <a href="{{ route('team.orders', $toggleQuery) }}"
               class="btn btn-primary" style="min-width: 140px;">
                არქივი
            </a>
        @else
            <a href="{{ route('team.orders', array_merge(['view' => 'archived'], $toggleQuery)) }}"
               class="btn btn-secondary" style="min-width: 140px;">
                არქივი
            </a>
        @endif
    </div>
    <div class="row">
        <div class="col-12">
            <div class="orders-grid">
                <div class="row" id="ordersGridRow" data-dnd-storage-key="{{ $dragStorageKey }}">
                @forelse($orders as $order)
                    <div class="col-md-3 col-sm-6 col-12 order-card" style="margin-bottom: 10px;" data-order-id="{{ $order->id }}">
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
                                @if($showArchived)
                                <button class="btn btn-danger btn-archive" onclick="unarchiveOrder({{ $order->id }})">
                                    დეარქივაცია
                                </button>
                                @else
                                <button class="btn btn-danger btn-archive" onclick="archiveOrder({{ $order->id }})">
                                    დაარქივება
                                </button>
                                @endif
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

{{-- Order Preview Modal --}}
<div id="orderPreviewOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
    <div style="position:relative; width:92vw; height:90vh; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,0.4);">
        <button id="orderPreviewClose" type="button" style="position:absolute; top:8px; right:12px; z-index:10; background:none; border:none; font-size:28px; cursor:pointer; color:#333; line-height:1;">&times;</button>
        <iframe id="orderPreviewIframe" style="width:100%; height:100%; border:none;"></iframe>
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
        var overlay = document.getElementById('orderPreviewOverlay');
        var iframe = document.getElementById('orderPreviewIframe');
        iframe.src = url;
        overlay.style.display = 'flex';
    }

    document.getElementById('orderPreviewClose').addEventListener('click', function() {
        var overlay = document.getElementById('orderPreviewOverlay');
        var iframe = document.getElementById('orderPreviewIframe');
        overlay.style.display = 'none';
        iframe.src = '';
    });

    document.getElementById('orderPreviewOverlay').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            document.getElementById('orderPreviewIframe').src = '';
        }
    });
    
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

    function unarchiveOrder(orderId) {
        if (!confirm('დარწმუნებული ხართ რომ გსურთ ამ შეკვეთის დეარქივაცია?')) {
            return;
        }

        const button = event.target;
        button.disabled = true;
        button.textContent = 'Processing...';

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("team.orders.unarchive", ":id") }}'.replace(':id', orderId);

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = token;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }

    function archiveOrder(orderId) {
        if (!confirm('დარწმუნებული ხართ რომ გსურთ ამ შეკვეთის არქივაცია?')) {
            return;
        }

        const button = event.target;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Archiving...';

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("team.orders.archive", ":id") }}'.replace(':id', orderId);

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = token;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>

@basset('https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js')
<script>
    (function() {
        var row = document.getElementById('ordersGridRow');
        if (!row) return;

        var storageKey = row.getAttribute('data-dnd-storage-key') || 'teamOrdersGridOrder:v1';

        function getCards() {
            return Array.prototype.slice.call(row.querySelectorAll('.order-card[data-order-id]'));
        }

        function loadOrder() {
            try {
                var raw = localStorage.getItem(storageKey);
                if (!raw) return;
                var ids = JSON.parse(raw);
                if (!Array.isArray(ids) || !ids.length) return;

                var byId = new Map();
                getCards().forEach(function(el) {
                    byId.set(String(el.getAttribute('data-order-id')), el);
                });

                ids.forEach(function(id) {
                    var el = byId.get(String(id));
                    if (el) row.appendChild(el);
                });
            } catch (e) {
                // ignore invalid saved state
            }
        }

        function saveOrder() {
            try {
                var ids = getCards().map(function(el) { return String(el.getAttribute('data-order-id')); });
                localStorage.setItem(storageKey, JSON.stringify(ids));
            } catch (e) {
                // ignore storage errors (private mode, quota, etc.)
            }
        }

        loadOrder();

        if (window.jQuery && typeof jQuery.fn.sortable === 'function') {
            jQuery(function($) {
                var $row = $(row);
                $row.sortable({
                    items: '.order-card[data-order-id]',
                    tolerance: 'pointer',
                    helper: 'clone',
                    opacity: 1,
                    forcePlaceholderSize: true,
                    placeholder: 'order-card-placeholder col-md-3 col-sm-6 col-12',
                    start: function(evt, ui) {
                        ui.item.addClass('dragging');
                    },
                    stop: function(evt, ui) {
                        ui.item.removeClass('dragging');
                        saveOrder();
                    }
                });

                $row.disableSelection();
            });
        }
    })();
</script>
@endpush
@endsection


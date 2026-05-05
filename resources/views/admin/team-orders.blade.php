@extends(backpack_view('blank'))

@section('content')
@php
    $isTeamUser = backpack_user() && backpack_user()->hasRole('team');
    $dragStorageKey = 'teamOrdersGridOrder:v1:' . (backpack_user()->id ?? 'guest');
    $showArchived = $showArchived ?? false;
    $dateFrom = $dateFrom ?? request()->query('from');
    $dateTo = $dateTo ?? request()->query('to');
    $productFilter = $productFilter ?? [];
    if (!is_array($productFilter)) {
        $productFilter = $productFilter === 'all' ? [] : [$productFilter];
    }
    $clientFilter = $clientFilter ?? 'all';
    $products = $products ?? collect();
    $clients = $clients ?? collect();

    $toggleQuery = array_filter([
        'from' => $dateFrom ?: null,
        'to' => $dateTo ?: null,
        'client' => ($clientFilter !== 'all') ? $clientFilter : null,
    ]);
    if (!empty($productFilter)) {
        $toggleQuery['product'] = $productFilter;
    }
@endphp
<style>

    body {
        background-color:rgb(54, 54, 54);
        font-size: 16px;
        overflow-x: hidden;
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

    footer, .main-footer {
        display: none !important;
    }

    .container-fluid {
        max-width: 100vw;
        overflow-x: hidden;
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
        overflow: hidden;
        container-type: inline-size;
    }

    .order-card .order-tile {
        cursor: grab;
        user-select: none;
        -webkit-user-select: none;
        -webkit-user-drag: none;
        touch-action: pan-y;
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
        font-size: clamp(11px, 5cqw, 14px);
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
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .order-actions .btn-archive {
        margin-left: auto;
    }
    
    .order-actions .btn {
        padding: 4px 10px;
        font-size: 12px;
        white-space: nowrap;
    }
    
    .created-at {
        font-size: clamp(11px, 5cqw, 14px);
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
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px;
        background-color: #6081b3;
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        max-width: 100%;
    }
    
    .size-tag.ready {
        background-color: #198754;
    }

    .service-shortname-tag {
        display: inline-block;
        background-color: #4a6fa5;
        color: #fff;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .size-tag.ready .service-shortname-tag {
        background-color: #146c43;
    }

    .piece-dots-btn {
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        font-size: 16px;
        padding: 0 4px;
        line-height: 1;
        opacity: 0.7;
        position: relative;
    }
    .piece-dots-btn:hover {
        opacity: 1;
    }
    .piece-ctx-menu {
        display: none;
        position: fixed;
        background: #fff;
        border: 1px solid #dadcde;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9000;
        min-width: 130px;
        overflow: hidden;
    }
    .piece-ctx-menu.open {
        display: block;
    }
    .piece-ctx-menu-item {
        display: block;
        width: 100%;
        padding: 8px 14px;
        background: none;
        border: none;
        text-align: left;
        font-size: 13px;
        color: #198754;
        font-weight: 600;
        cursor: pointer;
    }
    .piece-ctx-menu-item:hover {
        background: #f0f4f8;
    }

    .size-tags-empty {
        color: #999;
        font-size: 11px;
        font-style: italic;
    }

    /* Archive drop zone / button */
    .archive-drop-zone {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
        border: 2px solid rgba(255, 255, 255, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        transition: all 0.25s ease;
        text-decoration: none;
        cursor: pointer;
    }
    .archive-drop-zone .archive-icon {
        font-size: 24px;
        color: rgba(255, 255, 255, 0.6);
        transition: all 0.25s ease;
    }
    .archive-drop-zone:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.4);
    }
    .archive-drop-zone:hover .archive-icon {
        color: rgba(255, 255, 255, 0.9);
    }
    .archive-drop-zone.drag-active {
        width: 80px;
        height: 80px;
        background: rgba(220, 53, 69, 0.25);
        border: 3px dashed rgba(220, 53, 69, 0.6);
    }
    .archive-drop-zone.drag-active .archive-icon {
        font-size: 30px;
        color: rgba(220, 53, 69, 0.8);
    }
    .archive-drop-zone.drag-hover {
        width: 110px;
        height: 110px;
        background: rgba(220, 53, 69, 0.5);
        border-color: #dc3545;
        box-shadow: 0 0 30px rgba(220, 53, 69, 0.5);
    }
    .archive-drop-zone.drag-hover .archive-icon {
        font-size: 40px;
        color: #fff;
    }

    /* Undo toast */
    .archive-undo-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: #333;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 2000;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        transition: transform 0.3s ease;
        white-space: nowrap;
    }
    .archive-undo-toast.show {
        transform: translateX(-50%) translateY(0);
    }
    .archive-undo-toast .undo-btn {
        background: none;
        border: 1px solid rgba(255,255,255,0.4);
        color: #6ea8fe;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
    }
    .archive-undo-toast .undo-btn:hover {
        background: rgba(255,255,255,0.1);
    }

    /* Checkbox dropdown for multiselect */
    .checkbox-dropdown {
        position: relative;
        width: 200px;
    }
    .checkbox-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.4285714286;
        background-color: var(--tblr-bg-forms, #1a2234);
        color: var(--tblr-body-color, #dadcde);
        border: 1px solid var(--tblr-border-color, #2c3c56);
        border-radius: 4px;
        cursor: pointer;
        min-height: 38px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .checkbox-dropdown-toggle::after {
        content: '';
        border-top: 5px solid currentColor;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        margin-left: 8px;
        flex-shrink: 0;
        opacity: 0.5;
    }
    .checkbox-dropdown-toggle:hover {
        border-color: var(--tblr-border-color-active, #3a4f6f);
    }
    .checkbox-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        background: var(--tblr-bg-surface, #1a2234);
        border: 1px solid var(--tblr-border-color, #2c3c56);
        border-top: none;
        border-radius: 0 0 4px 4px;
        max-height: 220px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .checkbox-dropdown.open .checkbox-dropdown-menu {
        display: block;
    }
    .checkbox-dropdown.open .checkbox-dropdown-toggle {
        border-radius: 4px 4px 0 0;
        border-color: #90b5e2;
        box-shadow: 0 0 0 0.25rem rgba(32,107,196,0.25);
    }
    .checkbox-dropdown-menu label {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        margin: 0;
        cursor: pointer;
        font-size: 13px;
        color: var(--tblr-body-color, #dadcde);
    }
    .checkbox-dropdown-menu label:hover {
        background-color: rgba(255,255,255,0.05);
    }
    .checkbox-dropdown-menu input[type="checkbox"] {
        accent-color: #6081b3;
        width: 15px;
        height: 15px;
        flex-shrink: 0;
    }
</style>

<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-end pt-3 gap-2">
        @if($showArchived)
        <a href="{{ route('team.orders') }}" class="btn btn-outline-light" title="მთავარი">
            <i class="la la-home"></i>
        </a>
        @endif
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

            <div>
                <label class="form-label mb-1 text-light">მასალა</label>
                <div class="checkbox-dropdown" id="productDropdown">
                    <div class="checkbox-dropdown-toggle" id="productDropdownToggle">ყველა</div>
                    <div class="checkbox-dropdown-menu">
                        @foreach($products as $product)
                        <label>
                            <input type="checkbox" name="product[]" value="{{ $product->id }}" {{ in_array($product->id, $productFilter) ? 'checked' : '' }}>
                            {{ $product->title }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div>
                <label class="form-label mb-1 text-light">კლიენტი</label>
                <select class="form-select" name="client" style="width: 200px;" autocomplete="off">
                    <option value="all" {{ $clientFilter === 'all' ? 'selected' : '' }}>ყველა</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ $clientFilter == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ $showArchived ? route('team.orders', ['view' => 'archived']) : route('team.orders') }}" class="btn btn-outline-light">Reset</a>
        </form>
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
                                    <div class="col-md-2 order-id">#{{ $order->id }}</div>
                                    <div class="col-md-3 order-status">{!! status_badge($order->status) !!}</div>
                                    <div class="col-md-7 created-at">{{ $order->created_at->format('Y-m-d H:i') }}</div>
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
                                                'service_shortnames' => [],
                                                'all_ready' => true
                                            ];
                                        }
                                        $sizeGroups[$key]['quantity'] += $piece->quantity ?? 1;
                                        $sizeGroups[$key]['piece_ids'][] = $piece->id;
                                        if ($piece->status !== 'ready') {
                                            $sizeGroups[$key]['all_ready'] = false;
                                        }
                                        
                                        // Get services associated with this piece
                                        $pieceServices = $order->services->filter(function($service) use ($piece) {
                                            return $service->pivot->piece_id == $piece->id;
                                        })->sortBy('id');
                                        
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
                                            <span class="size-tag {{ $size['all_ready'] ? 'ready' : '' }}" data-piece-ids="{{ implode(',', $size['piece_ids']) }}">
                                                {{ $size['width'] }} × {{ $size['height'] }} cm (×{{ $size['quantity'] }})
                                                @if(count($size['service_shortnames']) > 0)
                                                    @foreach($size['service_shortnames'] as $shortname)
                                                        <span class="service-shortname-tag">{{ $shortname }}</span>
                                                    @endforeach
                                                @endif
                                                @if(!$size['all_ready'])
                                                <span class="piece-dots-btn" onclick="togglePieceMenu(event, this)">⋮</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="size-tags-empty">No sizes available</div>
                                @endif
                            </div>
                            
                            <div class="order-actions">
                                <button class="btn btn-primary" onclick="previewOrder('{{ url(config("backpack.base.route_prefix") . "/order/" . $order->id . "/show") }}', {{ $order->pieces->count() }})">
                                    ნახვა
                                </button>
                                <button class="btn btn-success" onclick="finishOrder({{ $order->id }})">
                                    დასრულება
                                </button>
                                @if($order->atachment)
                                <a href="{{ asset('storage/' . $order->atachment) }}" target="_blank" class="btn" style="background-color: #e67e22; border-color: #e67e22; color: #fff;">
                                    <i class="la la-file-download"></i>
                                </a>
                                @endif
                                @if($showArchived)
                                <button class="btn btn-danger btn-archive" onclick="unarchiveOrder({{ $order->id }})">
                                    დეარქივაცია
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

{{-- Shared piece context menu --}}
<div id="pieceCtxMenu" class="piece-ctx-menu">
    <button type="button" class="piece-ctx-menu-item" id="pieceCtxMenuReady">დასრულება</button>
</div>

@if(!$showArchived)
<a href="{{ route('team.orders', array_merge(['view' => 'archived'], $toggleQuery)) }}" id="archiveDropZone" class="archive-drop-zone" title="არქივი">
    <span class="archive-icon"><i class="la la-archive"></i></span>
</a>
<div id="archiveUndoToast" class="archive-undo-toast">
    <span id="archiveUndoText"></span>
    <button class="undo-btn" id="archiveUndoBtn">გაუქმება</button>
</div>
@endif

{{-- Order Preview Modal --}}
<div id="orderPreviewOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
    <div id="orderPreviewDialog" style="position:relative; height:90vh; background:#fff; border-radius:10px; box-shadow:0 8px 40px rgba(0,0,0,0.4); transition: width 0.2s ease;">
        <button id="orderPreviewClose" type="button" style="position:absolute; top:-18px; right:-18px; z-index:99999; background:#fff; border:2px solid #ccc; font-size:18px; cursor:pointer; color:#333; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; line-height:1; box-shadow:0 2px 8px rgba(0,0,0,0.3); pointer-events:auto;">&times;</button>
        <iframe id="orderPreviewIframe" style="width:100%; height:100%; border:none; border-radius:10px;"></iframe>
    </div>
</div>

@push('after_scripts')
<script>
(function() {
    var dd = document.getElementById('productDropdown');
    var toggle = document.getElementById('productDropdownToggle');
    if (!dd || !toggle) return;

    function updateLabel() {
        var checked = dd.querySelectorAll('input[type="checkbox"]:checked');
        if (checked.length === 0) {
            toggle.textContent = 'ყველა';
        } else {
            var names = [];
            checked.forEach(function(cb) { names.push(cb.parentElement.textContent.trim()); });
            toggle.textContent = names.join(', ');
        }
    }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dd.classList.toggle('open');
    });

    dd.querySelector('.checkbox-dropdown-menu').addEventListener('click', function(e) {
        e.stopPropagation();
    });

    dd.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
        cb.addEventListener('change', updateLabel);
    });

    document.addEventListener('click', function() {
        dd.classList.remove('open');
    });

    updateLabel();
})();
</script>

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
    function previewOrder(url, pieceCount) {
        var overlay = document.getElementById('orderPreviewOverlay');
        var dialog = document.getElementById('orderPreviewDialog');
        var iframe = document.getElementById('orderPreviewIframe');

        if (pieceCount <= 1) {
            dialog.style.width = '500px';
            dialog.style.maxWidth = '95vw';
        } else if (pieceCount <= 3) {
            dialog.style.width = '75vw';
            dialog.style.maxWidth = '95vw';
        } else {
            dialog.style.width = '92vw';
            dialog.style.maxWidth = '95vw';
        }

        iframe.src = url;
        overlay.style.display = 'flex';
    }

    function closePreview() {
        var overlay = document.getElementById('orderPreviewOverlay');
        var iframe = document.getElementById('orderPreviewIframe');
        overlay.style.display = 'none';
        iframe.src = '';
    }

    document.getElementById('orderPreviewClose').addEventListener('click', closePreview);

    document.getElementById('orderPreviewOverlay').addEventListener('click', function(e) {
        if (e.target === this) closePreview();
    });

    var _pieceCtxMenu = document.getElementById('pieceCtxMenu');
    var _pieceCtxTag = null;

    function togglePieceMenu(e, dotsBtn) {
        e.stopPropagation();
        var tag = dotsBtn.closest('.size-tag');
        var wasOpen = _pieceCtxMenu.classList.contains('open') && _pieceCtxTag === tag;

        _pieceCtxMenu.classList.remove('open');

        if (!wasOpen) {
            _pieceCtxTag = tag;
            var rect = dotsBtn.getBoundingClientRect();
            _pieceCtxMenu.style.top = (rect.bottom + 4) + 'px';
            _pieceCtxMenu.style.left = rect.left + 'px';
            _pieceCtxMenu.classList.add('open');
        }
    }

    document.addEventListener('click', function() {
        _pieceCtxMenu.classList.remove('open');
    });

    document.getElementById('pieceCtxMenuReady').addEventListener('click', function(e) {
        e.stopPropagation();
        if (!_pieceCtxTag) return;

        var tag = _pieceCtxTag;
        var pieceIds = tag.getAttribute('data-piece-ids').split(',');
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;

        _pieceCtxMenu.classList.remove('open');

        var dotsBtn = tag.querySelector('.piece-dots-btn');
        if (dotsBtn) { dotsBtn.style.opacity = '0.3'; dotsBtn.style.pointerEvents = 'none'; }

        var done = 0;
        pieceIds.forEach(function(id) {
            fetch('{{ route("team.pieces.ready", ":id") }}'.replace(':id', id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function() {
                done++;
                if (done === pieceIds.length) {
                    tag.classList.add('ready');
                    if (dotsBtn) dotsBtn.remove();
                }
            });
        });
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

    function archiveOrderAjax(orderId, $card, callback) {
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;

        fetch('{{ route("team.orders.archive", ":id") }}'.replace(':id', orderId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function() { if (callback) callback(true); })
        .catch(function() { if (callback) callback(false); });
    }

    function unarchiveOrderAjax(orderId, callback) {
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;

        fetch('{{ route("team.orders.unarchive", ":id") }}'.replace(':id', orderId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function() { if (callback) callback(true); })
        .catch(function() { if (callback) callback(false); });
    }
</script>

@basset('https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js')
@basset('https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js')
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
                var $dropZone = $('#archiveDropZone');
                var $undoToast = $('#archiveUndoToast');
                var $undoText = $('#archiveUndoText');
                var $undoBtn = $('#archiveUndoBtn');
                var undoTimer = null;

                $row.sortable({
                    items: '.order-card[data-order-id]',
                    cancel: 'a,button,input,textarea,select,option,.order-actions',
                    delay: 150,
                    distance: 10,
                    tolerance: 'pointer',
                    helper: 'clone',
                    opacity: 1,
                    forcePlaceholderSize: true,
                    placeholder: 'order-card-placeholder col-md-3 col-sm-6 col-12',
                    start: function(evt, ui) {
                        ui.item.addClass('dragging');
                        $dropZone.addClass('drag-active');
                    },
                    stop: function(evt, ui) {
                        ui.item.removeClass('dragging');
                        $dropZone.removeClass('drag-active drag-hover');
                        saveOrder();
                    }
                });

                $row.disableSelection();

                if ($dropZone.length && typeof $.fn.droppable === 'function') {
                    $dropZone.droppable({
                        accept: '.order-card[data-order-id]',
                        tolerance: 'touch',
                        over: function() {
                            $dropZone.addClass('drag-hover');
                        },
                        out: function() {
                            $dropZone.removeClass('drag-hover');
                        },
                        drop: function(evt, ui) {
                            var $card = ui.draggable;
                            var orderId = $card.attr('data-order-id');
                            if (!orderId) return;

                            $row.sortable('cancel');
                            $dropZone.removeClass('drag-active drag-hover');

                            var cardHtml = $card[0].outerHTML;
                            var $next = $card.next();
                            var $parent = $card.parent();

                            $card.css({ transition: 'opacity 0.3s, transform 0.3s', opacity: 0, transform: 'scale(0.8)' });
                            setTimeout(function() { $card.remove(); saveOrder(); }, 300);

                            archiveOrderAjax(orderId, $card, function(ok) {
                                if (!ok) {
                                    restoreCard(cardHtml, $next, $parent);
                                    return;
                                }
                                showUndoToast(orderId, cardHtml, $next, $parent);
                            });
                        }
                    });
                }

                function showUndoToast(orderId, cardHtml, $next, $parent) {
                    clearTimeout(undoTimer);
                    $undoText.text('#' + orderId + ' დაარქივებულია');
                    $undoToast.addClass('show');

                    $undoBtn.off('click').on('click', function() {
                        clearTimeout(undoTimer);
                        $undoToast.removeClass('show');
                        unarchiveOrderAjax(orderId, function() {
                            restoreCard(cardHtml, $next, $parent);
                        });
                    });

                    undoTimer = setTimeout(function() {
                        $undoToast.removeClass('show');
                    }, 5000);
                }

                function restoreCard(cardHtml, $next, $parent) {
                    var $restored = $(cardHtml).css({ opacity: 0 });
                    if ($next.length) {
                        $restored.insertBefore($next);
                    } else {
                        $parent.append($restored);
                    }
                    $restored.animate({ opacity: 1 }, 300);
                    saveOrder();
                }
            });
        }
    })();
</script>
@endpush
@endsection


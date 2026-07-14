@extends(backpack_view('blank'))

@section('content')
@php
    $isTeamUser = backpack_user() && backpack_user()->hasRole('team');
    $dragStorageKey = 'teamOrdersGridOrder:v1:' . (backpack_user()->id ?? 'guest') . ':p' . (int) request()->query('page', 1);
    $showArchived = $showArchived ?? false;
    $dateFrom = $dateFrom ?? request()->query('from');
    $dateTo = $dateTo ?? request()->query('to');
    $productFilter = $productFilter ?? [];
    if (!is_array($productFilter)) {
        $productFilter = $productFilter === 'all' ? [] : [$productFilter];
    }
    $serviceFilter = $serviceFilter ?? [];
    if (!is_array($serviceFilter)) {
        $serviceFilter = $serviceFilter === 'all' ? [] : [$serviceFilter];
    }
    $stageFilter = $stageFilter ?? [];
    if (!is_array($stageFilter)) {
        $stageFilter = $stageFilter === 'all' ? [] : [$stageFilter];
    }
    $currentStageFilter = $currentStageFilter ?? [];
    if (!is_array($currentStageFilter)) {
        $currentStageFilter = $currentStageFilter === 'all' ? [] : [$currentStageFilter];
    }
    $clientFilter = $clientFilter ?? 'all';
    $products = $products ?? collect();
    $services = $services ?? collect();
    $stages = $stages ?? collect();
    $clients = $clients ?? collect();

    // Lookups for resolving a piece's relevant stages from its services.
    // A service belongs to a stage (services.stage_id); a piece's selectable
    // stages are the stages of its services PLUS the universal stages that
    // apply to every piece (e.g. მოჭრა, დასრულება).
    $stageNameById = $stages->pluck('name', 'id');
    $stageOrderSlugs = array_keys(piece_stages());
    $universalStageSlugs = array_keys(piece_universal_stages());

    $toggleQuery = array_filter([
        'from' => $dateFrom ?: null,
        'to' => $dateTo ?: null,
        'client' => ($clientFilter !== 'all') ? $clientFilter : null,
    ]);
    if (!empty($productFilter)) {
        $toggleQuery['product'] = $productFilter;
    }
    if (!empty($serviceFilter)) {
        $toggleQuery['service'] = $serviceFilter;
    }
    if (!empty($stageFilter)) {
        $toggleQuery['stage'] = $stageFilter;
    }
    if (!empty($currentStageFilter)) {
        $toggleQuery['current_stage'] = $currentStageFilter;
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

    .order-card.dragging .order-tile {
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
        position: relative;
        padding-right: 36px;
    }
    .order-header-no-actions {
        padding-right: 0;
    }

    .order-drag-handle {
        cursor: grab;
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
    }
    .order-card.dragging .order-drag-handle {
        cursor: grabbing;
    }

    .order-dots-btn {
        position: absolute;
        top: 0;
        right: 0;
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        font-size: 18px;
        padding: 4px 6px;
        min-width: 28px;
        min-height: 28px;
        line-height: 1;
        opacity: 0.7;
        z-index: 2;
        touch-action: manipulation;
    }
    .order-dots-btn:hover {
        opacity: 1;
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
        touch-action: manipulation;
    }
    .order-actions .btn-archive {
        margin-left: auto;
    }

    .order-actions:has(.btn-order-comment) .btn-archive {
        margin-left: 0;
    }
    
    .order-actions .btn-order-comment {
        margin-left: auto;
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
        font-weight: 600;
    }

    .order-actions .btn-order-comment:hover {
        background-color: #e0a800;
        border-color: #d39e00;
        color: #212529;
    }

    #orderCommentModal {
        z-index: 10050 !important;
    }
    
    .order-actions .btn {
        padding: 10px 14px;
        font-size: 13px;
        white-space: nowrap;
        min-height: 44px;
        touch-action: manipulation;
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

    /* Pagination bar (dark theme) */
    .team-pagination {
        display: flex;
        justify-content: center;
        padding: 8px 0 32px;
    }
    .team-pagination .pagination {
        margin: 0;
        flex-wrap: wrap;
        gap: 4px;
    }
    .team-pagination .page-link {
        background-color: var(--tblr-bg-forms, #1a2234);
        border-color: var(--tblr-border-color, #2c3c56);
        color: var(--tblr-body-color, #dadcde);
        min-width: 40px;
        min-height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        touch-action: manipulation;
    }
    .team-pagination .page-link:hover {
        background-color: #6081b3;
        border-color: #6081b3;
        color: #fff;
    }
    .team-pagination .page-item.active .page-link {
        background-color: #6081b3;
        border-color: #6081b3;
        color: #fff;
    }
    .team-pagination .page-item.disabled .page-link {
        background-color: rgba(255, 255, 255, 0.04);
        border-color: var(--tblr-border-color, #2c3c56);
        color: #6c757d;
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
        padding: 8px 12px;
        min-height: 40px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        max-width: 100%;
        cursor: pointer;
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
    }
    .size-tag.size-tag-readonly {
        cursor: default;
    }

    .size-tag.broken {
        box-shadow: 0 0 0 2px #dc3545;
    }

    .piece-broken-label {
        display: inline-block;
        margin-left: 4px;
        font-size: 11px;
        font-weight: 700;
        color: #5c1212;
        background-color: rgba(255, 255, 255, 0.92);
        padding: 1px 5px;
        border-radius: 3px;
        white-space: nowrap;
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
    .piece-dots-btn {
        background: none;
        border: none;
        color: inherit;
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
        min-width: 160px;
        max-height: 80vh;
        overflow-y: auto;
    }
    .piece-ctx-menu.open {
        display: block;
    }
    .piece-ctx-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 12px 14px;
        min-height: 44px;
        background: none;
        border: none;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        touch-action: manipulation;
    }
    .piece-ctx-menu-item i {
        width: 16px;
        text-align: center;
        font-size: 14px;
        flex-shrink: 0;
    }
    .piece-ctx-menu-item.item-cut {
        color: #997404;
    }
    .piece-ctx-menu-item.item-processed {
        color: #fd7e14;
    }
    .piece-ctx-menu-item.item-ready {
        color: #0d6efd;
    }
    .piece-ctx-menu-item.item-broken {
        color: #dc3545;
    }
    .piece-ctx-menu-item.item-finished {
        color: #198754;
    }
    .piece-ctx-menu-item.item-stage {
        color: #2c3e50;
    }
    /* CSS checkbox drawn in the item's stage color (currentColor). */
    .piece-ctx-menu-item .stage-check {
        width: 18px;
        height: 18px;
        border: 2px solid currentColor;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-sizing: border-box;
        opacity: 0.7;
    }
    .piece-ctx-menu-item.checked .stage-check {
        background: currentColor;
        opacity: 1;
    }
    .piece-ctx-menu-item.checked .stage-check::after {
        content: '';
        width: 5px;
        height: 9px;
        border: solid #fff;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
        margin-top: -2px;
    }
    .piece-ctx-menu-item.checked {
        background: #f4f8ff;
    }
    .piece-ctx-menu-item.item-stage-clear {
        color: #97a0af;
    }
    .piece-ctx-menu-empty {
        display: none;
        padding: 12px 14px;
        font-size: 12px;
        font-style: italic;
        color: #97a0af;
    }
    .piece-ctx-menu-item:hover {
        background: #f0f4f8;
    }
    .piece-ctx-menu-item + .piece-ctx-menu-item {
        border-top: 1px solid #f0f0f0;
    }
    .piece-ctx-menu-label {
        padding: 8px 14px 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #97a0af;
        border-top: 2px solid #e9edf2;
        margin-top: 2px;
        background: #f7f9fb;
    }
    .piece-ctx-menu-label:first-child {
        border-top: none;
        margin-top: 0;
        border-radius: 6px 6px 0 0;
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

    #clientFilterSelect + .select2-container {
        width: 240px !important;
    }
    #clientFilterSelect + .select2-container .select2-selection--single {
        min-height: 38px;
        background-color: var(--tblr-bg-forms, #1a2234);
        border-color: var(--tblr-border-color, #2c3c56);
        color: var(--tblr-body-color, #dadcde);
    }
    #clientFilterSelect + .select2-container .select2-selection__rendered {
        color: var(--tblr-body-color, #dadcde);
        line-height: 36px;
    }
    #clientFilterSelect + .select2-container .select2-selection__arrow b {
        border-color: var(--tblr-body-color, #dadcde) transparent transparent transparent;
    }

    .team-user-bar {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 1001;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
    }

    .team-user-name {
        color: var(--tblr-body-color, #dadcde);
        font-weight: 600;
    }

    .team-logout-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .select2-container--open .select2-dropdown--below {
        background: var(--tblr-bg-surface, #1a2234);
        border-color: var(--tblr-border-color, #2c3c56);
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        background: var(--tblr-bg-forms, #1a2234);
        border-color: var(--tblr-border-color, #2c3c56);
        color: var(--tblr-body-color, #dadcde);
    }
    .select2-container--default .select2-results__option {
        color: var(--tblr-body-color, #dadcde);
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #6081b3;
    }
</style>

<div class="team-user-bar">
    <span class="team-user-name">{{ backpack_user()->name }}</span>
    <a href="{{ route('backpack.auth.logout') }}" class="btn btn-outline-light team-logout-btn" title="გასვლა">
        <i class="la la-sign-out-alt"></i>
        <span>გასვლა</span>
    </a>
</div>

<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-end pt-3 gap-2">
        @if($showArchived)
        <a href="{{ route('team.orders') }}" class="btn btn-outline-light" title="მთავარი">
            <i class="la la-home"></i>
        </a>
        @endif
        <form method="GET" action="{{ route('team.orders') }}" class="d-flex flex-wrap align-items-end gap-2" autocomplete="off">
            <input type="hidden" name="applied" value="1" />
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
                <label class="form-label mb-1 text-light">სერვისები</label>
                <div class="checkbox-dropdown" id="serviceDropdown">
                    <div class="checkbox-dropdown-toggle" id="serviceDropdownToggle">ყველა</div>
                    <div class="checkbox-dropdown-menu">
                        @foreach($services as $service)
                        @php $serviceLabel = $service->shortname ?: $service->title; @endphp
                        <label>
                            <input type="checkbox" name="service[]" value="{{ $serviceLabel }}" {{ in_array($serviceLabel, $serviceFilter) ? 'checked' : '' }}>
                            {{ $serviceLabel }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div>
                <label class="form-label mb-1 text-light">ჩემი ეტაპი</label>
                <div class="checkbox-dropdown" id="stageDropdown">
                    <div class="checkbox-dropdown-toggle" id="stageDropdownToggle">ყველა</div>
                    <div class="checkbox-dropdown-menu">
                        @foreach($stages as $stage)
                        <label>
                            <input type="checkbox" name="stage[]" value="{{ $stage->name }}" {{ in_array($stage->name, $stageFilter) ? 'checked' : '' }}>
                            {{ $stage->title }}
                        </label>
                        @endforeach
                        <label>
                            <input type="checkbox" name="stage[]" value="__none__" {{ in_array('__none__', $stageFilter) ? 'checked' : '' }}>
                            ეტაპის გარეშე
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <label class="form-label mb-1 text-light">მიმდინარე ეტაპი</label>
                <div class="checkbox-dropdown" id="currentStageDropdown">
                    <div class="checkbox-dropdown-toggle" id="currentStageDropdownToggle">ყველა</div>
                    <div class="checkbox-dropdown-menu">
                        @foreach($stages as $stage)
                        <label>
                            <input type="checkbox" name="current_stage[]" value="{{ $stage->name }}" {{ in_array($stage->name, $currentStageFilter) ? 'checked' : '' }}>
                            {{ $stage->title }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div>
                <label class="form-label mb-1 text-light">კლიენტი</label>
                <select class="form-select" id="clientFilterSelect" name="client" style="width: 240px;" autocomplete="off">
                    <option value="all" {{ $clientFilter === 'all' ? 'selected' : '' }}>ყველა</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ $clientFilter == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('team.orders', $showArchived ? ['view' => 'archived', 'reset' => 1] : ['reset' => 1]) }}" class="btn btn-outline-light">Reset</a>
        </form>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="orders-grid{{ $showArchived ? ' orders-grid-archived' : '' }}">
                <div class="row" id="ordersGridRow" data-dnd-storage-key="{{ $dragStorageKey }}">
                @forelse($orders as $order)
                    <div class="col-md-3 col-sm-6 col-12 order-card" style="margin-bottom: 10px;" data-order-id="{{ $order->id }}">
                        <div class="order-tile">
                            <div class="order-header{{ $showArchived ? ' order-header-no-actions' : '' }}">
                                @if(!$showArchived)
                                <button type="button" class="order-dots-btn" onclick="toggleOrderMenu(event, this)" title="მოქმედებები">⋮</button>
                                <div class="order-drag-handle">
                                @endif
                                <div class="row">
                                    <div class="col-md-2 order-id">#{{ $order->id }}</div>
                                    <div class="col-md-3 order-status">{!! status_badge($order->status) !!}</div>
                                    <div class="col-md-7 created-at">{{ $order->created_at->format('Y-m-d H:i') }}</div>
                                </div>
                                @if(!$showArchived)
                                </div>
                                @endif
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

                                    // On the team page, the displayed size includes the cutting
                                    // allowance. The Cutting Size setting is in mm; piece sizes are
                                    // in cm, so convert (mm / 10) and add it to each dimension.
                                    $cuttingCm = ((float) setting('cutting_size', 0)) / 10;

                                    $sizeGroups = [];
                                    foreach($piecesWithSizes as $piece) {
                                        // Highest completed stage (from the piece_stage pivot) drives
                                        // the tag colour, exactly as the old cache column did.
                                        $pieceStage = $piece->currentStageName();
                                        $displayWidth = number_format($piece->width + $cuttingCm, 1);
                                        $displayHeight = number_format($piece->height + $cuttingCm, 1);
                                        $key = $displayWidth . 'x' . $displayHeight;
                                        if (!isset($sizeGroups[$key])) {
                                            $sizeGroups[$key] = [
                                                'width' => $displayWidth,
                                                'height' => $displayHeight,
                                                'quantity' => 0,
                                                'piece_ids' => [],
                                                'service_shortnames' => [],
                                                'stage_slugs' => [],
                                                'completed_slugs' => $piece->completedStageNames(),
                                                'broken_count' => 0,
                                                'stage' => $pieceStage,
                                                'stage_mixed' => false,
                                            ];
                                        } else {
                                            if ($sizeGroups[$key]['stage'] !== $pieceStage) {
                                                $sizeGroups[$key]['stage_mixed'] = true;
                                            }
                                            // A stage checkbox is only "checked" for the group when
                                            // every piece in it has completed that stage.
                                            $sizeGroups[$key]['completed_slugs'] = array_values(
                                                array_intersect($sizeGroups[$key]['completed_slugs'], $piece->completedStageNames())
                                            );
                                        }
                                        $sizeGroups[$key]['quantity'] += $piece->quantity ?? 1;
                                        $sizeGroups[$key]['piece_ids'][] = $piece->id;
                                        $sizeGroups[$key]['broken_count'] += $piece->getBrokenCount();
                                        
                                        // Get services associated with this piece
                                        $pieceServices = $order->services->filter(function($service) use ($piece) {
                                            return $service->pivot->piece_id == $piece->id;
                                        })->sortBy('id');
                                        
                                        // Collect unique service shortnames + the stages those
                                        // services belong to (the piece's selectable stages).
                                        foreach($pieceServices as $service) {
                                            if ($service->shortname && !in_array($service->shortname, $sizeGroups[$key]['service_shortnames'])) {
                                                $sizeGroups[$key]['service_shortnames'][] = $service->shortname;
                                            }

                                            $stageSlug = $stageNameById[$service->stage_id] ?? null;
                                            if ($stageSlug && !in_array($stageSlug, $sizeGroups[$key]['stage_slugs'])) {
                                                $sizeGroups[$key]['stage_slugs'][] = $stageSlug;
                                            }
                                        }
                                    }
                                    $uniqueSizes = array_values($sizeGroups);
                                @endphp
                                
                                @if(count($uniqueSizes) > 0)
                                    <div class="size-tags">
                                        @foreach($uniqueSizes as $size)
                                            @php
                                                $groupStage = (!$size['stage_mixed'] && $size['stage']) ? $size['stage'] : null;
                                                $stageStyle = $groupStage
                                                    ? 'background-color: ' . piece_stage_color($groupStage) . '; color: ' . piece_stage_text_color($groupStage) . ';'
                                                    : 'background-color: ' . piece_draft_color() . '; color: #ffffff;';
                                                // The stages this size group can move through, in canonical order:
                                                // the universal stages plus the stages of its services.
                                                $groupStageSlugs = array_values(array_filter($stageOrderSlugs, fn ($slug) => in_array($slug, $size['stage_slugs'], true) || in_array($slug, $universalStageSlugs, true)));
                                                // Stages completed by every piece in the group, in canonical order.
                                                $groupCompletedSlugs = array_values(array_filter($stageOrderSlugs, fn ($slug) => in_array($slug, $size['completed_slugs'] ?? [], true)));
                                            @endphp
                                            <span class="size-tag{{ ($size['broken_count'] ?? 0) > 0 ? ' broken' : '' }}{{ $groupStage ? ' has-stage' : '' }}{{ $showArchived ? ' size-tag-readonly' : '' }}" data-piece-ids="{{ implode(',', $size['piece_ids']) }}" data-piece-stage="{{ $groupStage ?? '' }}" data-piece-stages="{{ implode(',', $groupStageSlugs) }}" data-piece-completed="{{ implode(',', $groupCompletedSlugs) }}" style="{{ $stageStyle }}"@if(!$showArchived) onclick="togglePieceMenu(event, this)"@endif>
                                                {{ $size['width'] }} × {{ $size['height'] }} cm (×{{ $size['quantity'] }})
                                                @if(($size['broken_count'] ?? 0) > 0)
                                                    <span class="piece-broken-label">[გატყდა: {{ $size['broken_count'] }}]</span>
                                                @endif
                                                @if(count($size['service_shortnames']) > 0)
                                                    @foreach($size['service_shortnames'] as $shortname)
                                                        <span class="service-shortname-tag">{{ $shortname }}</span>
                                                    @endforeach
                                                @endif
                                                @if(!$showArchived)
                                                <span class="piece-dots-btn" aria-hidden="true">⋮</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="size-tags-empty">No sizes available</div>
                                @endif
                            </div>
                            
                            <div class="order-actions">
                                @if($showArchived)
                                <button type="button" class="btn btn-danger btn-archive" onclick="unarchiveOrder({{ $order->id }})">
                                    დეარქივაცია
                                </button>
                                @else
                                @if($order->atachment)
                                <a href="{{ asset('storage/' . $order->atachment) }}" target="_blank" class="btn" style="background-color: #e67e22; border-color: #e67e22; color: #fff;">
                                    <i class="la la-file-download"></i>
                                </a>
                                @endif
                                @if($order->comment)
                                <button type="button" class="btn btn-order-comment" data-comment="{{ e($order->comment) }}" onclick="showOrderComment(this)">
                                    <i class="la la-sticky-note"></i> შენიშვნა
                                </button>
                                @endif
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

            @if($orders->hasPages())
                <div class="team-pagination">
                    {{ $orders->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Shared piece context menu --}}
@if(!$showArchived)
<div id="pieceCtxMenu" class="piece-ctx-menu">
    <div class="piece-ctx-menu-label" id="pieceCtxMenuHint">მონიშნეთ დასრულებული ეტაპი</div>
    @foreach(piece_stages() as $stageSlug => $stageLabel)
        {{-- The final 'completion' (დასრულება) stage auto-passes once every other
             stage is done (no one is responsible for it), so it is not offered
             as a manual toggle here. --}}
        @continue($stageSlug === 'completion')
        <button type="button" class="piece-ctx-menu-item item-stage" data-stage="{{ $stageSlug }}" style="color: {{ piece_stage_color($stageSlug) }};"><span class="stage-check" aria-hidden="true"></span> {{ $stageLabel }}</button>
    @endforeach
    <div class="piece-ctx-menu-empty" id="pieceCtxMenuEmpty">ეტაპები არ არის მიბმული</div>
    <button type="button" class="piece-ctx-menu-item item-stage-clear" data-stage=""><i class="la la-eraser"></i> ეტაპის მოხსნა</button>
</div>
@endif

{{-- Order comment modal --}}
<div class="modal fade" id="orderCommentModal" tabindex="-1" aria-labelledby="orderCommentModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderCommentModalLabel">შენიშვნა</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="orderCommentModalText" class="mb-0" style="white-space: pre-wrap;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">დახურვა</button>
            </div>
        </div>
    </div>
</div>

{{-- Order card context menu --}}
@if(!$showArchived)
<div id="orderCtxMenu" class="piece-ctx-menu">
    <button type="button" class="piece-ctx-menu-item item-finished" id="orderCtxMenuFinished"><i class="la la-sign-out-alt"></i> გატანილია</button>
</div>
@endif

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

@push('after_styles')
    @basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css')
@endpush

@push('after_scripts')
    @basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js')
<script>
jQuery(function($) {
    var clientSelect = document.getElementById('clientFilterSelect');
    if (!clientSelect || !$.fn.select2) return;

    var $clientSelect = $(clientSelect);
    $clientSelect.select2({
        width: '240px',
        minimumResultsForSearch: 0,
        dropdownParent: $clientSelect.parent()
    });
    $clientSelect.on('select2:open', function() {
        setTimeout(function() {
            var search = document.querySelector('.select2-container--open .select2-search__field');
            if (search) search.focus();
        }, 50);
    });
});
</script>
<script>
(function() {
    function initCheckboxDropdown(ddId, toggleId) {
        var dd = document.getElementById(ddId);
        var toggle = document.getElementById(toggleId);
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
            document.querySelectorAll('.checkbox-dropdown.open').forEach(function(openDd) {
                if (openDd !== dd) openDd.classList.remove('open');
            });
            dd.classList.toggle('open');
        });

        dd.querySelector('.checkbox-dropdown-menu').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        dd.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', updateLabel);
        });

        updateLabel();
    }

    initCheckboxDropdown('productDropdown', 'productDropdownToggle');
    initCheckboxDropdown('serviceDropdown', 'serviceDropdownToggle');
    initCheckboxDropdown('stageDropdown', 'stageDropdownToggle');
    initCheckboxDropdown('currentStageDropdown', 'currentStageDropdownToggle');

    document.addEventListener('click', function() {
        document.querySelectorAll('.checkbox-dropdown.open').forEach(function(dd) {
            dd.classList.remove('open');
        });
    });
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

    function showOrderComment(btn, textOverride) {
        var text = (typeof textOverride === 'string') ? textOverride : ((btn && btn.getAttribute('data-comment')) || '');
        var textEl = document.getElementById('orderCommentModalText');
        var modalEl = document.getElementById('orderCommentModal');
        if (!textEl || !modalEl || typeof bootstrap === 'undefined') return;

        textEl.textContent = text;

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function liftModalAboveOverlay() {
            modalEl.style.zIndex = '10050';
            var backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) {
                backdrops[backdrops.length - 1].style.zIndex = '10040';
            }
        }

        modalEl.addEventListener('shown.bs.modal', liftModalAboveOverlay, { once: true });
        modal.show();
    }

    document.getElementById('orderPreviewClose').addEventListener('click', closePreview);

    document.getElementById('orderPreviewOverlay').addEventListener('click', function(e) {
        if (e.target === this) closePreview();
    });

@if(!$showArchived)
    var _pieceCtxMenu = document.getElementById('pieceCtxMenu');
    var _pieceCtxTag = null;
    var _orderCtxMenu = document.getElementById('orderCtxMenu');
    var _orderCtxCard = null;

    // The stage slugs visible for the currently open group, in completion order.
    // Populated on open from the size tag's data-piece-stages attribute.
    var _activeStageOrder = [];
    var _pieceCtxMenuHint = document.getElementById('pieceCtxMenuHint');
    var _pieceCtxMenuEmpty = document.getElementById('pieceCtxMenuEmpty');

    function closeCtxMenus() {
        _pieceCtxMenu.classList.remove('open');
        _orderCtxMenu.classList.remove('open');
    }

    function toggleOrderMenu(e, dotsBtn) {
        e.stopPropagation();
        var card = dotsBtn.closest('.order-card');
        var wasOpen = _orderCtxMenu.classList.contains('open') && _orderCtxCard === card;

        closeCtxMenus();

        if (!wasOpen) {
            _orderCtxCard = card;
            var rect = dotsBtn.getBoundingClientRect();

            _orderCtxMenu.style.top = '0px';
            _orderCtxMenu.style.left = Math.max(8, rect.right - 130) + 'px';
            _orderCtxMenu.classList.add('open');

            var menuH = _orderCtxMenu.offsetHeight;
            var spaceBelow = window.innerHeight - rect.bottom;
            var top;
            if (spaceBelow < menuH + 8 && rect.top > spaceBelow) {
                top = Math.max(8, rect.top - menuH - 4);
            } else {
                top = rect.bottom + 4;
            }
            _orderCtxMenu.style.top = top + 'px';
        }
    }

    function togglePieceMenu(e, el) {
        e.stopPropagation();
        var tag = el.classList.contains('size-tag') ? el : el.closest('.size-tag');
        if (!tag) return;

        var wasOpen = _pieceCtxMenu.classList.contains('open') && _pieceCtxTag === tag;

        closeCtxMenus();

        if (!wasOpen) {
            _pieceCtxTag = tag;

            // Only show the stages relevant to this piece — the stages of the
            // services attached to it plus universal stages (data-piece-stages,
            // in canonical order).
            var allowed = (tag.getAttribute('data-piece-stages') || '')
                .split(',').filter(function(s) { return s !== ''; });
            _activeStageOrder = allowed;

            // Each stage is an independent checkbox: checked when the group has
            // COMPLETED it (data-piece-completed), i.e. a dated pivot record exists
            // for every piece in the group.
            var completed = (tag.getAttribute('data-piece-completed') || '')
                .split(',').filter(function(s) { return s !== ''; });

            _pieceCtxMenu.querySelectorAll('.item-stage').forEach(function(item) {
                var slug = item.getAttribute('data-stage');
                item.style.display = (allowed.indexOf(slug) !== -1) ? '' : 'none';
                item.classList.toggle('checked', completed.indexOf(slug) !== -1);
            });

            // Toggle the hint / empty note depending on whether any stage applies.
            if (_pieceCtxMenuHint) _pieceCtxMenuHint.style.display = allowed.length ? '' : 'none';
            if (_pieceCtxMenuEmpty) _pieceCtxMenuEmpty.style.display = allowed.length ? 'none' : 'block';

            var anchor = tag.querySelector('.piece-dots-btn') || tag;
            var rect = anchor.getBoundingClientRect();

            // Render first so we can measure the menu, then position it.
            _pieceCtxMenu.style.top = '0px';
            _pieceCtxMenu.style.left = '0px';
            _pieceCtxMenu.classList.add('open');

            var menuH = _pieceCtxMenu.offsetHeight;
            var menuW = _pieceCtxMenu.offsetWidth;
            var spaceBelow = window.innerHeight - rect.bottom;

            // Flip above the anchor when it would overflow the bottom edge
            // and there's more room above.
            var top;
            if (spaceBelow < menuH + 8 && rect.top > spaceBelow) {
                top = Math.max(8, rect.top - menuH - 4);
            } else {
                top = rect.bottom + 4;
            }

            // Keep it inside the right edge too.
            var left = Math.min(rect.left, window.innerWidth - menuW - 8);
            if (left < 8) left = 8;

            _pieceCtxMenu.style.top = top + 'px';
            _pieceCtxMenu.style.left = left + 'px';
        }
    }

    document.addEventListener('click', function() {
        closeCtxMenus();
    });

    function reloadTeamPageIfStatusUpdated(success, errorMessage) {
        if (success) {
            window.location.reload();
            return;
        }
        if (errorMessage) {
            alert(errorMessage);
        }
    }

    document.getElementById('orderCtxMenuFinished').addEventListener('click', function(e) {
        e.stopPropagation();
        if (!_orderCtxCard) return;

        var orderId = _orderCtxCard.getAttribute('data-order-id');
        closeCtxMenus();
        finishOrder(orderId, e.currentTarget);
    });

    // Toggle a single stage's completion for every piece in the size group.
    // `stage` empty + completed omitted clears all stages. Otherwise `completed`
    // (true/false) records or removes that stage's dated completion.
    function applyStageToPieces(tag, stage, completed) {
        var pieceIds = tag.getAttribute('data-piece-ids').split(',');
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;

        closeCtxMenus();

        var done = 0;
        var failed = false;
        pieceIds.forEach(function(id) {
            var formData = new FormData();
            formData.append('_token', token);
            formData.append('stage', stage);
            if (stage !== '') {
                formData.append('completed', completed ? '1' : '0');
            }
            fetch('{{ route("team.pieces.stage", ":id") }}'.replace(':id', id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) failed = true;
            })
            .catch(function() { failed = true; })
            .finally(function() {
                done++;
                if (done === pieceIds.length) {
                    reloadTeamPageIfStatusUpdated(!failed, 'ეტაპი – მოთხოვნა ვერ შესრულდა.');
                }
            });
        });
    }

    // Checkbox behaviour: each stage is independent. Clicking checks (records a
    // dated completion) or unchecks (removes it) that stage for the whole group.
    _pieceCtxMenu.querySelectorAll('.item-stage').forEach(function(stageBtn) {
        stageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!_pieceCtxTag) return;

            var slug = stageBtn.getAttribute('data-stage');
            if (_activeStageOrder.indexOf(slug) < 0) return;

            var willComplete = !stageBtn.classList.contains('checked');
            applyStageToPieces(_pieceCtxTag, slug, willComplete);
        });
    });

    // Clear all completed stages for the group.
    _pieceCtxMenu.querySelectorAll('.item-stage-clear').forEach(function(clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!_pieceCtxTag) return;
            applyStageToPieces(_pieceCtxTag, '');
        });
    });

@endif
    function finishOrder(orderId, triggerEl) {
        if (!confirm('დარწმუნებული ხართ რომ შეკვეთა გატანილია?')) {
            return;
        }

        var button = triggerEl || (typeof event !== 'undefined' ? event.target : null);
        if (button && button.tagName === 'BUTTON') {
            button.disabled = true;
            button.textContent = 'Processing...';
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("team.orders.finish", ":id") }}'.replace(':id', orderId);

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = token;
        form.appendChild(csrfInput);

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

@if(!$showArchived)
<script>
    // Auto-reload: periodically ask the server which orders currently match the
    // active filters. If a newly *confirmed* order appears that is not on the
    // page yet, reload so the team sees it. Uses the current URL's query string
    // so the check respects whatever filter is applied.
    (function() {
        var POLL_INTERVAL = 10000; // ms

        var checkUrl = '{{ route('team.orders.check') }}';
        var search = window.location.search; // preserves applied filters (?applied=1&stage[]=...)
        var pollUrl = checkUrl + (search ? search : '');

        // --- Notification chime (Web Audio API; no external sound file) ------
        var audioCtx = null;
        function ensureAudioCtx() {
            if (audioCtx) return audioCtx;
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) audioCtx = new Ctx();
            return audioCtx;
        }
        // Browsers block audio until the page receives a user gesture. Firefox
        // in particular stays muted for later timer-triggered sound unless the
        // context is both resumed AND primed (a silent blip played) inside a
        // real gesture. Do that on the first interactions, then stop listening
        // once the context is confirmed running.
        var unlockEvents = ['pointerdown', 'click', 'keydown', 'touchstart'];
        function unlockAudio() {
            var ctx = ensureAudioCtx();
            if (!ctx) return;
            if (ctx.state !== 'running' && ctx.resume) ctx.resume();
            try {
                var src = ctx.createBufferSource();
                src.buffer = ctx.createBuffer(1, 1, 22050); // 1 silent sample
                src.connect(ctx.destination);
                src.start(0);
            } catch (e) { /* priming is best-effort */ }
            if (ctx.state === 'running') {
                unlockEvents.forEach(function(evt) {
                    document.removeEventListener(evt, unlockAudio);
                });
            }
        }
        unlockEvents.forEach(function(evt) {
            document.addEventListener(evt, unlockAudio, { passive: true });
        });

        function playNotification() {
            var ctx = ensureAudioCtx();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume();

            var now = ctx.currentTime;
            // A standard ascending four-note chime (C6–E6–G6–C7 major arpeggio)
            // at full volume, with the final note held a touch longer. A master
            // gain feeds the output so the overlapping notes stay loud without
            // clipping into distortion.
            var master = ctx.createGain();
            master.gain.value = 1.0; // maximum
            master.connect(ctx.destination);

            [
                { f: 1046.50, t: 0,    d: 0.5 },
                { f: 1318.51, t: 0.18, d: 0.5 },
                { f: 1567.98, t: 0.36, d: 0.5 },
                { f: 2093.00, t: 0.54, d: 0.8 }
            ].forEach(function(note) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'triangle'; // bell-like, richer than a pure sine
                osc.frequency.value = note.f;
                osc.connect(gain);
                gain.connect(master);
                var start = now + note.t;
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(1.0, start + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + note.d);
                osc.start(start);
                osc.stop(start + note.d + 0.05);
            });
        }

        function currentOrderIds() {
            var ids = {};
            document.querySelectorAll('.order-card[data-order-id]').forEach(function(el) {
                ids[String(el.getAttribute('data-order-id'))] = true;
            });
            return ids;
        }

        // Don't reload while the user is mid-interaction — it would be jarring
        // and could lose in-progress work (dragging, an open menu, a preview).
        function isBusy() {
            if (document.hidden) return true;
            if (document.querySelector('.order-card.dragging')) return true;
            if (document.querySelector('.piece-ctx-menu.open')) return true;
            var overlay = document.getElementById('orderPreviewOverlay');
            if (overlay && overlay.style.display !== 'none' && overlay.style.display !== '') return true;
            if (document.querySelector('.modal.show')) return true;
            return false;
        }

        function poll() {
            if (isBusy()) return;

            fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) {
                if (!data || !Array.isArray(data.ids)) return;
                if (isBusy()) return; // re-check: state may have changed during the request

                var shown = currentOrderIds();
                var hasNewCard = data.ids.some(function(id) {
                    return !shown[String(id)];
                });

                if (hasNewCard) {
                    // Chime first, then reload — the reload would otherwise cut
                    // the sound off before it is audible.
                    playNotification();
                    setTimeout(function() { window.location.reload(); }, 1500);
                }
            })
            .catch(function() { /* transient network error — try again next tick */ });
        }

        setInterval(poll, POLL_INTERVAL);
    })();
</script>
@endif

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

@if(!$showArchived)
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
                    handle: '.order-drag-handle',
                    cancel: 'a,button,input,textarea,select,option,.order-actions,.order-dots-btn,.piece-dots-btn,.size-tag,.size-tags,.piece-ctx-menu',
                    delay: 150,
                    distance: 8,
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

                $row.find('.order-drag-handle').disableSelection();

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
@endif
    })();
</script>
@endpush
@endsection


@extends(backpack_view('blank'))

@push('after_styles')
<style>
    @page { size: A4; margin: 10mm; }
    @media print {
        aside.navbar, .navbar, .d-print-none, .btn { display: none !important; }
        body { padding: 0; background: #fff; }
        .invoice-container {
            box-shadow: none; border: none;
            padding: 10px !important;
            margin: 0 !important;
        }
        .invoice-table-section { margin-bottom: 8px !important; page-break-inside: avoid; }
        /* The sizes+services table can run past a page: let it break, but
           only between sizes so a size never splits from its services. */
        .invoice-table-section--breakable { page-break-inside: auto; }
        .invoice-table .piece-group { page-break-inside: avoid; break-inside: avoid; }
        .invoice-table .service-desc { padding-left: 18px !important; }
        .invoice-table th, .invoice-table td { padding: 4px 6px !important; font-size: 11px !important; }
        .invoice-table-heading { margin: 6px 0 4px 0 !important; font-size: 12px !important; }
    }
    aside.navbar { display: none !important; }
    .navbar-expand-lg.navbar-vertical ~ .navbar,
    .navbar-expand-lg.navbar-vertical ~ .page-wrapper { margin-left: 0 !important; }
    .invoice-container {
        max-width: 210mm;
        margin: 20px auto;
        padding: 14px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        color: #000;
    }
    .invoice-header {
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 2px solid #333;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-start;
    }
    .company-block { flex: 1; min-width: 180px; }
    .company-name { font-size: 15px; font-weight: bold; margin-bottom: 4px; color: #000; }
    .company-details { font-size: 11px; color: #000; line-height: 1.4; }
    .bank-section {
        flex: 1;
        min-width: 200px;
        margin: 0;
        padding: 6px 8px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 11px;
        color: #000;
        line-height: 1.4;
    }
    .bank-section strong { display: block; margin-bottom: 4px; color: #000; font-size: 12px; }
    .invoice-title { font-size: 18px; font-weight: bold; margin: 8px 0; color: #000; }
    .invoice-meta { margin-bottom: 10px; font-size: 12px; color: #000; }
    .invoice-meta table { width: 100%; }
    .invoice-meta td { padding: 3px 8px 3px 0; color: #000; line-height: 1.4; }
    .invoice-meta .label { font-weight: 600; color: #000; width: 140px; }
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 6px 0;
        font-size: 11px;
        color: #000;
    }
    .invoice-table th, .invoice-table td {
        border: 1px solid #ccc;
        padding: 5px 8px;
        text-align: left;
        color: #000;
        line-height: 1.35;
    }
    .invoice-table th { background: #f0f0f0; font-weight: 600; color: #000; }
    .invoice-table tfoot td { background: #f7f7f7; font-weight: 700; color: #000; }
    .invoice-table .piece-group + .piece-group tr:first-child td { border-top: 2px solid #999; }
    .invoice-table .piece-row td { font-weight: 600; }
    .invoice-table .service-row td { font-size: 10.5px; }
    .invoice-table .service-desc { padding-left: 18px; }
    .invoice-table .text-right { text-align: right; }
    .invoice-total { margin-top: 8px; text-align: right; font-size: 14px; font-weight: bold; color: #000; }
    .print-btn { margin-bottom: 10px; }
    .invoice-container .text-muted { color: #000 !important; }
    .invoice-table-heading { font-size: 12px; font-weight: bold; margin: 10px 0 4px 0; color: #000; }
    .invoice-table-section { margin-bottom: 10px; }
</style>
@endpush

@section('content')
<div class="invoice-container">
    <div class="print-btn d-print-none">
        <button type="button" class="btn btn-primary" onclick="window.print();">
            <i class="la la-print"></i> {{ __('Print') }}
        </button>
    </div>

    {{-- Company header: compact two-column --}}
    <div class="invoice-header">
        <div class="company-block">
            <div class="company-name">შ.პ.ს სარკის გალერეა</div>
            <div class="company-details">
                მისამართი: გ. ჭედიას №1, ხელოსნების ქალაქი, ავჭალა | ტელ: 555254433, 579980086, 551914400 | ID: 402190142 | mirrorgallery01@gmail.com
            </div>
        </div>
        <div class="bank-section">
            <strong>საბანკო ინფორმაცია</strong>
            მიმღები: შ.პ.ს სარკის გალერეა. საქართველოს ბანკი: GE32BG0000000539607936 | თბს ბანკი: GE51TB7642436020100007
        </div>
    </div>

    <div class="invoice-title">ინვოისი / Invoice</div>

    @php
        $order->load(['client', 'services', 'products', 'pieces' => fn ($q) => $q->orderBy('id'), 'pieces.product']);
        $totalGel = (float) ($order->price_gel ?? 0);
        $productLineItems = [];
        $productRowNum = 1;

        // Services: only pivot data from DB (no calculation). The measure column
        // varies per service (area / perimeter / length_cm / …), so both quantity
        // and unit are resolved from the pivot rather than from services.unit.
        $measureLabels = get_extra_field_labels();

        $serviceLine = function ($service) use ($measureLabels) {
            $measure = order_service_measure($service);
            $lineTotal = (float) ($service->pivot->price_gel ?? 0);
            $desc = $service->title;
            if (!empty($service->pivot->description)) {
                $desc .= ' – ' . $service->pivot->description;
            }

            // Services priced off several measures at once (e.g. განათება) can only
            // show one in the quantity column; the rest go into the description so
            // they are not dropped from the invoice.
            $extraMeasures = [];
            foreach (['tape_length' => 'მ', 'foam_length' => 'მ', 'sensor_quantity1' => 'ცალი'] as $field => $unit) {
                if ($field === $measure['field']) {
                    continue;
                }
                if (!in_array($field, $service->extra_field_names ?? [], true)) {
                    continue;
                }
                $value = $service->pivot->{$field} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $extraMeasures[] = ($measureLabels[$field] ?? $field) . ': ' . (0 + $value) . ' ' . $unit;
            }
            if ($extraMeasures) {
                $desc .= ' (' . implode(', ', $extraMeasures) . ')';
            }

            return [
                'description' => $desc,
                'qty' => $measure['qty'],
                'unit' => $measure['unit'],
                'total_gel' => $lineTotal,
            ];
        };

        // Products table: list each product from the order (price from order_product pivot)
        foreach ($order->products as $product) {
            $pivotPrice = isset($product->pivot->price) ? (float) $product->pivot->price : null;
            $productLineItems[] = [
                'num' => $productRowNum++,
                'description' => $product->title,
                'qty' => null,
                'unit' => '—',
                'price_gel' => $pivotPrice,
                'total_gel' => null,
            ];
        }

        // Sizes and services in one table: each size (piece) is followed by the
        // services attached to it (order_service.piece_id), the same nesting the
        // order form uses. Piece price comes from the pieces table (glass only).
        $pieceGroups = [];
        $pieceRowNum = 1;
        $orderPieceIds = $order->pieces->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($order->pieces as $piece) {
            $pieceGroups[] = [
                'num' => $pieceRowNum++,
                'description' => ($piece->width ?? '') . '×' . ($piece->height ?? '') . ' სმ',
                'qty' => (float) ($piece->quantity ?? 1),
                'area' => (float) $piece->getArea(),
                'price_gel' => (float) ($piece->price ?? 0),
                'services' => $order->services
                    ->filter(fn ($s) => $s->pivot->piece_id !== null && (int) $s->pivot->piece_id === (int) $piece->id)
                    ->map($serviceLine)
                    ->values()
                    ->all(),
            ];
        }

        // Services not tied to any size on this order: service-only orders, and
        // orders saved before services were nested under sizes (2026-07).
        $looseServiceItems = $order->services
            ->filter(fn ($s) => $s->pivot->piece_id === null || !in_array((int) $s->pivot->piece_id, $orderPieceIds, true))
            ->map($serviceLine)
            ->values()
            ->all();

        // Per-table summaries. Pieces carry the area; services are priced per
        // measure (metres, pieces, …) that cannot be summed, so they only get a
        // price total.
        $pieceTotalArea = 0.0;
        $pieceTotalQty = 0.0;
        $pieceTotalPrice = 0.0;
        foreach ($order->pieces as $piece) {
            $pieceTotalArea += (float) $piece->getArea();
            $pieceTotalQty += (float) ($piece->quantity ?? 1);
            $pieceTotalPrice += (float) ($piece->price ?? 0);
        }

        // Every product on the order is billed over the same sheet area (multi
        // product orders such as lamix / glass_pkg share the pieces), so the
        // products summary shows that shared area, not a per-product sum.
        $productTotalArea = $pieceTotalArea;
        $productTotalPrice = 0.0;
        foreach ($productLineItems as $item) {
            $productTotalPrice += (float) ($item['price_gel'] ?? 0);
        }

        $serviceTotalPrice = (float) $order->services->sum(fn ($s) => (float) ($s->pivot->price_gel ?? 0));

        // 2 → "2", 2.50 → "2.5", null → "—"
        $fmtQty = fn ($qty) => $qty === null ? '—' : rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
    @endphp

    <div class="invoice-meta">
        <table>
            <tr>
                <td class="label">შეკვეთის ნომერი:</td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td class="label">თარიღი:</td>
                <td>{{ $order->created_at ? $order->created_at->format('d.m.Y') : '—' }}</td>
            </tr>
            <tr>
                <td class="label">შეკვეთის ტიპი:</td>
                <td>{{ order_type_ge($order->order_type ?? '') }}</td>
            </tr>
            <tr>
                <td class="label">პროდუქციის ტიპი:</td>
                <td>{{ product_type_ge($order->product_type ?? '') }}</td>
            </tr>
            @if(in_array($order->product_type, ['lamix', 'glass_pkg']))
            <tr>
                <td class="label">შინაარსი:</td>
                <td>{{ $order->products->pluck('title')->implode(' x ') }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">მომხმარებელი:</td>
                <td>{{ $order->client ? $order->client->name : '—' }}</td>
            </tr>
            @if($order->client && ($order->client->personal_id || $order->client->legal_id))
            <tr>
                <td class="label">საიდენტიფიკაციო კოდი:</td>
                <td>{{ $order->client->legal_id ?? $order->client->personal_id }}</td>
            </tr>
            @endif
            @if($order->client && $order->client->address)
            <tr>
                <td class="label">მისამართი:</td>
                <td>{{ $order->client->address }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Table 1: Products --}}
    <div class="invoice-table-section">
        <div class="invoice-table-heading">პროდუქტები / Products</div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>აღწერა</th>
                    <th class="text-right">ფასი ($)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productLineItems as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td class="text-right">{{ $item['price_gel'] !== null ? number_format($item['price_gel'], 2) : '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">პოზიციები არ არის</td>
                </tr>
                @endforelse
            </tbody>
            @if(count($productLineItems))
            <tfoot>
                <tr>
                    <td>სულ (ფართობი: {{ number_format($productTotalArea, 2) }} m²)</td>
                    <td class="text-right">{{ number_format($productTotalPrice, 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Table 2: Sizes, each followed by its own services (as on the order form) --}}
    <div class="invoice-table-section invoice-table-section--breakable">
        <div class="invoice-table-heading">{{ count($pieceGroups) ? 'ზომები და მომსახურება' : 'მომსახურება / Services' }}</div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>აღწერა</th>
                    <th class="text-right">რაოდ.</th>
                    <th>ერთ.</th>
                    <th class="text-right">ფართობი</th>
                    <th class="text-right">ფასი (₾)</th>
                </tr>
            </thead>
            @foreach($pieceGroups as $group)
            <tbody class="piece-group">
                <tr class="piece-row">
                    <td>{{ $group['num'] }}</td>
                    <td>{{ $group['description'] }}</td>
                    <td class="text-right">{{ $fmtQty($group['qty']) }}</td>
                    <td>ც.</td>
                    <td class="text-right">{{ number_format($group['area'], 2) }} m²</td>
                    <td class="text-right">{{ number_format($group['price_gel'], 2) }}</td>
                </tr>
                @foreach($group['services'] as $item)
                <tr class="service-row">
                    <td></td>
                    <td class="service-desc">↳ {{ $item['description'] }}</td>
                    <td class="text-right">{{ $fmtQty($item['qty']) }}</td>
                    <td>{{ $item['unit'] ?? '—' }}</td>
                    <td></td>
                    <td class="text-right">{{ number_format($item['total_gel'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            @endforeach
            @if(count($looseServiceItems))
            <tbody class="piece-group">
                @if(count($pieceGroups))
                <tr class="piece-row">
                    <td></td>
                    <td colspan="5">საერთო მომსახურება</td>
                </tr>
                @endif
                @foreach($looseServiceItems as $item)
                <tr class="service-row">
                    <td>{{ count($pieceGroups) ? '' : $loop->iteration }}</td>
                    <td class="{{ count($pieceGroups) ? 'service-desc' : '' }}">{{ count($pieceGroups) ? '↳ ' : '' }}{{ $item['description'] }}</td>
                    <td class="text-right">{{ $fmtQty($item['qty']) }}</td>
                    <td>{{ $item['unit'] ?? '—' }}</td>
                    <td></td>
                    <td class="text-right">{{ number_format($item['total_gel'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            @endif
            @if(!count($pieceGroups) && !count($looseServiceItems))
            <tbody>
                <tr>
                    <td colspan="6" class="text-center text-muted">პოზიციები არ არის</td>
                </tr>
            </tbody>
            @endif
            @if(count($pieceGroups) || count($looseServiceItems))
            <tfoot>
                @if(count($pieceGroups))
                <tr>
                    <td colspan="2">ზომები სულ</td>
                    <td class="text-right">{{ $fmtQty($pieceTotalQty) }}</td>
                    <td>ც.</td>
                    <td class="text-right">{{ number_format($pieceTotalArea, 2) }} m²</td>
                    <td class="text-right">{{ number_format($pieceTotalPrice, 2) }}</td>
                </tr>
                @endif
                @if(count($order->services))
                <tr>
                    <td colspan="5">მომსახურება სულ</td>
                    <td class="text-right">{{ number_format($serviceTotalPrice, 2) }}</td>
                </tr>
                @endif
            </tfoot>
            @endif
        </table>
    </div>

    <div class="invoice-total">
        სულ: {{ number_format($totalGel, 2) }} ₾
    </div>
</div>
@endsection

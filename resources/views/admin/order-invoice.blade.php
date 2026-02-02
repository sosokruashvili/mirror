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
        $order->load(['client', 'services', 'products', 'pieces.product']);
        $totalGel = (float) ($order->price_gel ?? 0);
        $serviceLineItems = [];
        $productLineItems = [];
        $pieceLineItems = [];
        $serviceRowNum = 1;
        $productRowNum = 1;
        $pieceRowNum = 1;

        // Services: only pivot data from DB (no calculation)
        foreach ($order->services as $service) {
            $qty = (float) ($service->pivot->quantity ?? 1);
            $lineTotal = (float) ($service->pivot->price_gel ?? 0);
            $desc = $service->title;
            if (!empty($service->pivot->description)) {
                $desc .= ' – ' . $service->pivot->description;
            }
            $serviceLineItems[] = [
                'num' => $serviceRowNum++,
                'description' => $desc,
                'qty' => $qty,
                'unit' => $service->unit ?? 'ც.',
                'price_gel' => null,
                'total_gel' => $lineTotal,
            ];
        }

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

        // Pieces: only DB fields (price from pieces table)
        foreach ($order->pieces as $piece) {
            $product = $piece->product;
            $productTitle = $product ? $product->title : '—';
            $desc = ($piece->width ?? '') . '×' . ($piece->height ?? '') . ' სმ';
            $piecePrice = isset($piece->price) ? (float) $piece->price : null;
            $pieceLineItems[] = [
                'num' => $pieceRowNum++,
                'description' => $desc,
                'qty' => (float) ($piece->quantity ?? 1),
                'unit' => 'ც.',
                'price_gel' => $piecePrice,
                'total_gel' => null,
                'piece' => $piece,
            ];
        }
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
                    <th class="text-right">ფასი (₾)</th>
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
        </table>
    </div>

    {{-- Table 2: Pieces --}}
    <div class="invoice-table-section">
        <div class="invoice-table-heading">ზომები</div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>აღწერა</th>
                    <th class="text-right">რაოდ.</th>
                    <th class="text-right">ფართობი</th>
                    <th class="text-right">ფასი (₾)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pieceLineItems as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td class="text-right">{{ $item['qty'] !== null ? (is_numeric($item['qty']) && $item['qty'] == (int)$item['qty'] ? (int)$item['qty'] : $item['qty']) : '—' }}</td>
                    <td> {{ $item['piece']->getArea() }} m²</td>
                    <td class="text-right">{{ $item['piece']->price }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">პოზიციები არ არის</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Table 3: Services --}}
    <div class="invoice-table-section">
        <div class="invoice-table-heading">მომსახურება / Services</div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>აღწერა</th>
                    <th class="text-right">რაოდ.</th>
                    <th>ერთ.</th>
                    <th class="text-right">ფასი (₾)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($serviceLineItems as $item)
                <tr>
                    <td>{{ $item['num'] }}</td>
                    <td>{{ $item['description'] }}</td>
                    <td class="text-right">{{ is_numeric($item['qty']) && $item['qty'] == (int)$item['qty'] ? (int)$item['qty'] : $item['qty'] }}</td>
                    <td>{{ $item['unit'] }}</td>
                    <td class="text-right">{{ $item['total_gel'] !== null ? number_format($item['total_gel'], 2) : '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">პოზიციები არ არის</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="invoice-total">
        სულ: {{ number_format($totalGel, 2) }} ₾
    </div>
</div>
@endsection

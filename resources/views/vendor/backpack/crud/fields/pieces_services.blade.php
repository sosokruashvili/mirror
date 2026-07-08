{{--
    Custom field: nested Pieces → Services UI.

    Renders piece "cards", each of which contains its own list of services, replacing the
    two separate `pieces` and `services` Backpack repeatables. It submits the SAME flat
    payload the controller already understands:

        pieces[i][id|width|height|quantity]
        services[j][service_id|piece_id|...extra pivot fields]

    where each service's piece_id is `temp_i` (the DOM index of its parent piece card),
    matching the temp-id mapping in OrderCrudController::store()/update().

    Service-only orders (product_type = 'service') hide the piece cards and use a single
    order-level services list (services with no piece_id).
--}}
@php
    use App\Models\Service;

    $entry = $crud->getCurrentEntry();

    // --- Services available for selection (id, title, price and which extra fields they use)
    $servicesList = Service::orderBy('id')->get()->map(function ($service) {
        $extra = $service->extra_field_names;
        if (is_string($extra)) {
            $extra = json_decode($extra, true) ?? [];
        }
        return [
            'id' => $service->id,
            'title' => $service->title,
            'extra_field_names' => is_array($extra) ? array_values($extra) : [],
        ];
    })->values();

    // --- Build the initial data the JS will hydrate from.
    // Priority: old() input (validation error) → existing entry (edit) → empty (create).
    $pivotFields = ['quantity', 'description', 'color', 'light_type', 'price_gel', 'distance',
        'length_cm', 'perimeter', 'area', 'antifog_type', 'foam_length', 'tape_length',
        'sensor_type', 'sensor_quantity1'];

    $initialPieces = [];
    $initialOrderServices = [];

    if (old('pieces') !== null || old('services') !== null) {
        // Re-populate after a failed validation. Services carry piece_id = temp_{cardIndex}.
        $oldPieces = old('pieces', []);
        $oldServices = old('services', []);

        $indexMap = [];
        foreach (array_values($oldPieces) as $i => $piece) {
            $initialPieces[$i] = [
                'id' => $piece['id'] ?? null,
                'width' => $piece['width'] ?? null,
                'height' => $piece['height'] ?? null,
                'quantity' => $piece['quantity'] ?? null,
                'services' => [],
            ];
            $indexMap['temp_' . $i] = $i;
        }

        foreach ($oldServices as $service) {
            if (empty($service['service_id'])) {
                continue;
            }
            $row = ['service_id' => $service['service_id']];
            foreach ($pivotFields as $f) {
                $row[$f] = $service[$f] ?? null;
            }
            $pieceKey = $service['piece_id'] ?? null;
            if ($pieceKey !== null && $pieceKey !== '' && isset($indexMap[$pieceKey])) {
                $initialPieces[$indexMap[$pieceKey]]['services'][] = $row;
            } else {
                $initialOrderServices[] = $row;
            }
        }
        $initialPieces = array_values($initialPieces);
    } elseif ($entry) {
        // Editing an existing order.
        $entry->load(['pieces', 'services']);

        $servicesByPiece = [];
        foreach ($entry->services as $service) {
            $row = ['service_id' => $service->id];
            foreach ($pivotFields as $f) {
                $row[$f] = $service->pivot->{$f} ?? null;
            }
            $pieceId = $service->pivot->piece_id;
            if ($pieceId) {
                $servicesByPiece[$pieceId][] = $row;
            } else {
                $initialOrderServices[] = $row;
            }
        }

        foreach ($entry->pieces as $piece) {
            $initialPieces[] = [
                'id' => $piece->id,
                'width' => $piece->width,
                'height' => $piece->height,
                'quantity' => $piece->quantity,
                'services' => $servicesByPiece[$piece->id] ?? [],
            ];
        }
    }

    $colorOptions = [
        'ოქროსფერი', 'ვერცხლისფერი', 'წითელი', 'თეთრი',
        'შავი', 'ლურჯი', 'მწვანე', 'ნაცრისფერი',
    ];
    $lightTypeOptions = ['cool' => 'ცივი', 'warm' => 'თბილი', 'neutral' => 'ნეიტრალური'];
    $sensorTypeOptions = ['touch' => 'Touch', 'ir' => 'IR'];
@endphp

@push('after_styles')
<style>
    /* Neutral grey overlay works on both light and dark themes: it darkens slightly on
       light, lightens slightly on dark — just enough to separate each piece. */
    .piece-card { border: 1px solid rgba(128, 128, 128, 0.25); }
    .piece-card .card-header {
        background: rgba(128, 128, 128, 0.12);
        font-weight: 600;
        border-bottom: 1px solid rgba(128, 128, 128, 0.2);
    }
    .service-row { background: transparent; }
    .ps-services-container:empty::after {
        content: 'No services yet — click "Add service".';
        color: var(--bs-secondary-color, #97a0af); font-size: .85rem; display: block; padding: .25rem 0;
    }
    /* Make the select2 service dropdown text match the theme's input color (white on dark)
       instead of the faint default. */
    #pieces-services-field .select2-selection__rendered,
    #pieces-services-field .select2-selection__rendered .select2-selection__placeholder {
        color: var(--bs-body-color, #fff) !important;
    }
</style>
@endpush

<div bp-field-wrapper="true"
     bp-field-name="{{ $field['name'] }}"
     bp-field-type="pieces_services"
     id="pieces-services-field">

    {{-- Piece cards live here (normal product types) --}}
    <div id="ps-pieces-section">
        <label class="mb-1">{{ $field['label'] ?? 'Pieces & Services' }}</label>
        <div id="ps-pieces-container"></div>
        <button type="button" class="btn btn-sm btn-primary" id="ps-add-piece-btn">
            <i class="la la-plus"></i> Add Piece
        </button>
        @if (isset($field['hint']))
            <p class="help-block mt-1">{!! $field['hint'] !!}</p>
        @endif
    </div>

    {{-- Order-level services (only for product_type = 'service') --}}
    <div id="ps-order-services-section" class="mt-2 d-none">
        <label class="mb-1">Services</label>
        <div id="ps-order-services-container" class="ps-services-container"></div>
        <button type="button" class="btn btn-sm btn-outline-primary ps-add-order-service-btn">
            <i class="la la-plus"></i> Add Service
        </button>
    </div>
</div>

{{-- ------------------------------------------------------------------ --}}
{{-- Templates (never submitted; cloned by JS)                          --}}
{{-- ------------------------------------------------------------------ --}}

<template id="ps-piece-card-template">
    <div class="piece-card card mb-3" data-piece-card>
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span>Piece #<span data-piece-num></span></span>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-piece>
                <i class="la la-trash"></i>
            </button>
        </div>
        <div class="card-body py-2">
            <input type="hidden" data-piece-field="id" value="">
            <div class="row">
                <div class="form-group col-md-4">
                    <label>Width (cm)</label>
                    <input type="number" class="form-control" data-piece-field="width" step="0.01" min="0" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Height (cm)</label>
                    <input type="number" class="form-control" data-piece-field="height" step="0.01" min="0" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Quantity</label>
                    <input type="number" class="form-control" data-piece-field="quantity" min="1" required>
                </div>
            </div>
            <div class="mt-1">
                <label class="fw-bold mb-1">Services</label>
                <div class="ps-services-container" data-services-container></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-service>
                    <i class="la la-plus"></i> Add service
                </button>
            </div>
        </div>
    </div>
</template>

<template id="ps-service-row-template">
    <div class="service-row border rounded p-2 mb-2" data-service-row>
        <div class="row align-items-end">
            <div class="form-group col-md-2 mb-1">
                <label>Service</label>
                <select class="form-control" data-svc-field="service_id">
                    <option value="">-</option>
                    @foreach ($servicesList as $service)
                        <option value="{{ $service['id'] }}">{{ $service['title'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-1 mb-1 svc-sub svc-sub-quantity d-none">
                <label>Quantity</label>
                <input type="number" class="form-control" data-svc-field="quantity" min="0">
            </div>
            <div class="form-group col-md-2 mb-1 svc-sub svc-sub-perimeter d-none">
                <label>Perimeter (m)</label>
                <input type="number" class="form-control" data-svc-field="perimeter" step="0.01" min="0">
            </div>
            <div class="form-group col-md-2 mb-1 svc-sub svc-sub-color d-none">
                <label>Color</label>
                <select class="form-control" data-svc-field="color">
                    <option value="">-</option>
                    @foreach ($colorOptions as $color)
                        <option value="{{ $color }}">{{ $color }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-1 mb-1 svc-sub svc-sub-light_type d-none">
                <label>Light Type</label>
                <select class="form-control" data-svc-field="light_type">
                    <option value="">-</option>
                    @foreach ($lightTypeOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 mb-1 svc-sub svc-sub-distance d-none">
                <label>Delivery Distance (km)</label>
                <input type="number" class="form-control" data-svc-field="distance" step="1" min="0">
            </div>
            <div class="form-group col-md-2 mb-1 svc-sub svc-sub-length_cm d-none">
                <label>Length (cm)</label>
                <input type="number" class="form-control" data-svc-field="length_cm" step="1" min="0">
            </div>
            <div class="form-group col-md-2 mb-1 svc-sub svc-sub-area d-none">
                <label>Area (m²)</label>
                <input type="number" class="form-control" data-svc-field="area" step="0.01" min="0">
            </div>
            <div class="form-group col-md-1 mb-1 svc-sub svc-sub-tape_length d-none">
                <label>Tape Length (m)</label>
                <input type="number" class="form-control" data-svc-field="tape_length" step="0.01" min="0">
            </div>
            <div class="form-group col-md-2 mb-1 svc-sub svc-sub-antifog_type d-none">
                <label>Anti Fog Type</label>
                <input type="text" class="form-control" data-svc-field="antifog_type">
            </div>
            <div class="form-group col-md-1 mb-1 svc-sub svc-sub-sensor_quantity1 d-none">
                <label>Sensor Qty</label>
                <input type="number" class="form-control" data-svc-field="sensor_quantity1" min="0" max="2">
            </div>
            <div class="form-group col-md-1 mb-1 svc-sub svc-sub-sensor_type d-none">
                <label>Sensor Type</label>
                <select class="form-control" data-svc-field="sensor_type">
                    <option value="">-</option>
                    @foreach ($sensorTypeOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-1 mb-1 svc-sub svc-sub-foam_length d-none">
                <label>Foam Length (m)</label>
                <input type="number" class="form-control" data-svc-field="foam_length" step="0.01" min="0">
            </div>

            <div class="form-group col-md-2 mb-1 svc-price d-none">
                <label>Price (GEL)</label>
                <input type="number" class="form-control" data-svc-field="price_gel" step="0.01">
            </div>
            <div class="form-group col-md-2 mb-1 svc-calc-btn d-none">
                <button type="button" class="btn btn-primary calculate-price-btn">Calculate Price</button>
            </div>
            <div class="form-group col-md-1 mb-1">
                <button type="button" class="btn btn-outline-danger" data-remove-service title="Remove service">
                    <i class="la la-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    window.orderPiecesServicesData = {
        services: {!! $servicesList->toJson() !!},
        initialPieces: @json($initialPieces),
        initialOrderServices: @json($initialOrderServices),
    };
</script>

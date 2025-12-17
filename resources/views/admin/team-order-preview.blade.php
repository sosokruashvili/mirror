@extends(backpack_view('blank'))

@push('after_styles')
    <style>

		body {
			background-color:rgb(54, 54, 54);
		}
		.theme-dark .service-item {
			background:rgb(231, 231, 231) !important;
		}

		/* Ensure borders are visible in light mode */
		.border,
		.border-top,
		.border-bottom,
		.border-left,
		.border-right {
			border-color: #e0e0e0 !important;
		}
		
        aside.navbar {
			display: none !important;
		}

		.navbar-expand-lg.navbar-vertical~.navbar, .navbar-expand-lg.navbar-vertical~.page-wrapper {
			margin-left: 0 !important;
		}
		
		.piece-tile {
			background: #fff;
			border: 2px solid #e0e0e0;
			border-radius: 8px;
			padding: 15px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			transition: transform 0.2s, box-shadow 0.2s;
			display: flex;
			flex-direction: column;
			height: 100%;
			margin-bottom: 20px;
		}
		
		
		.piece-tile:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,0,0,0.15);
		}
		
		.piece-header {
			border-bottom: 2px solid #f0f0f0;
			padding-bottom: 10px;
			margin-bottom: 12px;
		}
		
		.piece-title {
			font-size: 18px;
			font-weight: bold;
			color: #2c3e50;
			margin-bottom: 8px;
		}
		
		.piece-details {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin-bottom: 12px;
			font-size: 13px;
		}
		
		.piece-detail-item {
			display: flex;
			align-items: center;
			gap: 5px;
		}
		
		.piece-detail-label {
			font-weight: 600;
			color: #666;
		}
		
		.piece-detail-value {
			color: #2c3e50;
		}
		
		.services-section {
			margin-top: 12px;
			padding-top: 12px;
			border-top: 1px solid #f0f0f0;
		}
		
		.services-title {
			font-size: 14px;
			font-weight: 600;
			color: #2c3e50;
			margin-bottom: 8px;
		}
		
		.service-item {
			background: #f8f9fa;
			border-left: 3px solid #6081b3;
			padding: 8px 12px;
			margin-bottom: 8px;
			border-radius: 4px;
			font-size: 13px;
		}
		
		.service-name {
			font-weight: 600;
			color: #2c3e50;
			margin-bottom: 4px;
		}
		
		.service-details {
			font-size: 12px;
			color: #666;
		}
		
		.service-detail-row {
			display: flex;
			justify-content: space-between;
			margin-top: 4px;
		}
		
		.no-services {
			color: #999;
			font-style: italic;
			font-size: 12px;
		}
		
		.order-info-section {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 15px;
			margin-bottom: 25px;
		}
		
		.order-info-row {
			display: flex;
			justify-content: space-between;
			padding: 6px 0;
			border-bottom: 1px solid #e0e0e0;
		}
		
		.order-info-row:last-child {
			border-bottom: none;
		}
		
		.order-info-label {
			font-weight: 600;
			color: #666;
		}
		
		.order-info-value {
			color: #2c3e50;
		}
		
		.pieces-grid {
			padding: 20px 0;
		}
		
		.no-pieces {
			text-align: center;
			padding: 40px;
			color: #999;
		}
		.order-info-value {
			font-size: 18px;
			font-weight: bold;
		}
    </style>
@endpush

@section('header')
    <div class="container-fluid d-flex justify-content-between my-3">
        <section class="header-operation animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
            <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</h1>
            <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">{!! $crud->getSubheading() ?? mb_ucfirst(trans('backpack::crud.preview')).' '.$crud->entity_name !!}</p>
            <p class="ms-2 ml-2 mb-0" bp-section="page-subheading-back-button">
                <small><a href="{{ route('team.orders') }}" class="font-sm"><i class="la la-angle-double-left"></i> Back to Order Processing</a></small>
            </p>
        </section>
        <a href="javascript: window.print();" class="btn float-end float-right"><i class="la la-print"></i></a>
    </div>
@endsection

@section('content')
@php
    $order = $entry;
    $order->load(['pieces.product', 'services', 'client']);
    
    // Sort pieces by ID
    $pieces = $order->pieces->sortBy('id');
    
    // Get services without a piece (piece_id is null)
    $servicesWithoutPiece = $order->services->filter(function($service) {
        return is_null($service->pivot->piece_id);
    });
@endphp

<div class="row" bp-section="crud-operation-show">
    <div class="{{ $crud->getShowContentClass() }}">

	{{-- Default box --}}
	<div class="">
	@if ($crud->model->translationEnabled())
		<div class="row">
			<div class="col-md-12 mb-2" bp-section="show-operation-language-dropdown">
				{{-- Change translation button group --}}
				<div class="btn-group float-right">
				<button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					{{trans('backpack::crud.language')}}: {{ $crud->model->getAvailableLocales()[request()->input('_locale')?request()->input('_locale'):App::getLocale()] }} &nbsp; <span class="caret"></span>
				</button>
				<ul class="dropdown-menu">
					@foreach ($crud->model->getAvailableLocales() as $key => $locale)
						<a class="dropdown-item" href="{{ url($crud->route.'/'.$entry->getKey().'/show') }}?_locale={{ $key }}">{{ $locale }}</a>
					@endforeach
				</ul>
				</div>
			</div>
		</div>
	@endif
	
	{{-- Order Information --}}
	<div class="order-info-section col-md-4">
		<div class="order-info-row">
			<span class="order-info-value">#{{ $order->id }}</span>
		</div>
		<div class="order-info-row">
			<span class="order-info-value">{{ $order->client->name ?? 'N/A' }}</span>
		</div>
		<div class="order-info-row">
			<span class="order-info-value">{!! status_badge($order->status) !!}</span>
		</div>
		<div class="order-info-row">
			<span class="order-info-value">{{ order_type_ge($order->order_type ?? '') }}</span>
		</div>
		<div class="order-info-row">
			<span class="order-info-value">{{ product_type_ge($order->product_type ?? '') }}</span>
		</div>
		<div class="order-info-row">
			<span class="order-info-value">{{ number_format($order->price_gel ?? $order->calculateTotalPrice(), 2) }} ₾</span>
		</div>
	</div>
	
	{{-- Pieces Grid --}}
	<div class="pieces-grid">
		@if($pieces->count() > 0)
			<div class="row">
				@foreach($pieces as $piece)
					@php
						// Get services for this piece
						$pieceServices = $order->services->filter(function($service) use ($piece) {
							return $service->pivot->piece_id == $piece->id;
						});
					@endphp
					<div class="col-md-4 col-sm-6 col-12">
						<div class="piece-tile {{ $piece->status === 'ready' ? 'border-success' : '' }}">
							<div class="piece-header d-flex justify-content-between">
								<div class="piece-title">Size: {{ $piece->width }} x {{ $piece->height }} cm</div>
								<div class="piece-title">X {{ $piece->quantity }}</div>
							</div>
							
							<div class="piece-details">
								@if($piece->product)
									<div class="piece-detail-item">
										<span class="piece-detail-label">Product:</span>
										<span class="piece-detail-value">{{ $piece->product->title ?? 'N/A' }}</span>
									</div>
								@endif
								<div class="piece-detail-item">
									<span class="piece-detail-label">Quantity:</span>
									<span class="piece-detail-value">{{ $piece->quantity ?? 1 }}</span>
								</div>
								@if($piece->width && $piece->height)
									<div class="piece-detail-item">
										<span class="piece-detail-label">Size:</span>
										<span class="piece-detail-value">{{ number_format($piece->width, 2) }} × {{ number_format($piece->height, 2) }} cm</span>
									</div>
									<div class="piece-detail-item">
										<span class="piece-detail-label">Area:</span>
										<span class="piece-detail-value">{{ number_format($piece->getArea(), 2) }} m²</span>
									</div>
								@endif
								@if($piece->status)
									<div class="piece-detail-item">
										<span class="piece-detail-label">Status:</span>
										{!! status_badge($piece->status) !!}
									</div>
								@endif
							</div>
							
							<div class="services-section">
								<div class="services-title">Services ({{ $pieceServices->count() }})</div>
								@if($pieceServices->count() > 0)
									@foreach($pieceServices as $service)
										<div class="service-item">
											<div class="service-name">{{ $service->title }}</div>
											<div class="service-details">
												@if($service->pivot->quantity)
													<div class="service-detail-row">
														<span>Quantity:</span>
														<span>{{ $service->pivot->quantity }}</span>
													</div>
												@endif
												@if($service->pivot->description)
													<div class="service-detail-row">
														<span>Description:</span>
														<span>{{ $service->pivot->description }}</span>
													</div>
												@endif
												@if($service->pivot->color)
													<div class="service-detail-row">
														<span>Color:</span>
														<span>{{ $service->pivot->color }}</span>
													</div>
												@endif
												@if($service->pivot->price_gel)
													<div class="service-detail-row">
														<span>Price:</span>
														<span>{{ number_format($service->pivot->price_gel, 2) }} ₾</span>
													</div>
												@endif
												@if($service->pivot->length_cm)
													<div class="service-detail-row">
														<span>Length:</span>
														<span>{{ number_format($service->pivot->length_cm, 2) }} cm</span>
													</div>
												@endif
												@if($service->pivot->distance)
													<div class="service-detail-row">
														<span>Distance:</span>
														<span>{{ number_format($service->pivot->distance, 2) }}</span>
													</div>
												@endif
											</div>
										</div>
									@endforeach
								@else
									<div class="no-services">No services assigned to this piece</div>
								@endif
							</div>
							
							<div class="d-flex justify-content-end mt-3 pt-3 border-top">
								@if($piece->status !== 'ready')
									<form method="POST" action="{{ route('team.pieces.ready', $piece->id) }}" class="d-inline">
										@csrf
										<button type="submit" class="btn btn-success btn-lg">
											<i class="la la-check"></i>&nbspReady
										</button>
									</form>
								@endif
							</div>
						</div>
					</div>
				@endforeach
			</div>
		@else
			<div class="no-pieces">
				<p>No pieces found for this order.</p>
			</div>
		@endif
		
		{{-- Services without a piece --}}
		@if($servicesWithoutPiece->count() > 0)
			<div class="mt-4">
				<h3 class="mb-3">Services (Not Assigned to Pieces)</h3>
				<div class="row">
					<div class="col-12">
						<div class="piece-tile">
							<div class="services-section">
								@foreach($servicesWithoutPiece as $service)
									<div class="service-item">
										<div class="service-name">{{ $service->title }}</div>
										<div class="service-details">
											@if($service->pivot->quantity)
												<div class="service-detail-row">
													<span>Quantity:</span>
													<span>{{ $service->pivot->quantity }}</span>
												</div>
											@endif
											@if($service->pivot->description)
												<div class="service-detail-row">
													<span>Description:</span>
													<span>{{ $service->pivot->description }}</span>
												</div>
											@endif
											@if($service->pivot->color)
												<div class="service-detail-row">
													<span>Color:</span>
													<span>{{ $service->pivot->color }}</span>
												</div>
											@endif
											@if($service->pivot->price_gel)
												<div class="service-detail-row">
													<span>Price:</span>
													<span>{{ number_format($service->pivot->price_gel, 2) }} ₾</span>
												</div>
											@endif
											@if($service->pivot->length_cm)
												<div class="service-detail-row">
													<span>Length:</span>
													<span>{{ number_format($service->pivot->length_cm, 2) }} cm</span>
												</div>
											@endif
											@if($service->pivot->distance)
												<div class="service-detail-row">
													<span>Distance:</span>
													<span>{{ number_format($service->pivot->distance, 2) }}</span>
												</div>
											@endif
										</div>
									</div>
								@endforeach
							</div>
						</div>
					</div>
				</div>
			</div>
		@endif
	</div>
		
	</div>
	</div>
</div>
@endsection

@push('after_scripts')
<script>
	// Force light theme on this page
	(function() {

	})();
</script>
@endpush


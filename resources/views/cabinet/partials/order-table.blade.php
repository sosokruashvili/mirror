<div class="table-responsive">
    <table class="table table-vcenter card-table table-hover">
        <thead>
            <tr>
                <th>{{ __('cabinet.order') }}</th>
                <th>{{ __('cabinet.status') }}</th>
                <th>{{ __('cabinet.created') }}</th>
                <th>{{ __('cabinet.due') }}</th>
                <th>{{ __('cabinet.paid') }}</th>
                <th class="text-end">{{ __('cabinet.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                @include('cabinet.partials.order-row', ['order' => $order])
            @empty
                <tr>
                    <td colspan="6" class="text-secondary">{{ __('cabinet.empty_orders') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

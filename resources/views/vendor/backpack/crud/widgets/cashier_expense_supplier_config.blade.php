{{-- Category-driven form config, consumed by cashier-expense-supplier-v4.js --}}
@push('after_scripts')
<script>
    window.cashierExpenseSupplierOptions = @json($widget['supplierOptions'] ?? []);
    window.cashierExpenseProductionCategoryIds = @json($widget['productionCategoryIds'] ?? []);
    window.cashierExpenseSupplierPrices = @json($widget['supplierPrices'] ?? []);
</script>
@endpush

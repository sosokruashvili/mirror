<?php

if (!function_exists('status_badge')) {
    /**
     * Generate a Bootstrap badge for order status
     *
     * @param string $status The status value (draft, new, pending, working, done, finished)
     * @param string|null $text Optional custom text to display instead of capitalized status
     * @return string HTML badge element
     */
    function status_badge(string $status, ?string $text = null): string
    {
        $status = strtolower($status);
        $displayText = $text ?? ucfirst($status);
        
        // Define badge classes for each status using Bootstrap 5/Tabler compatible classes
        // Using text-bg-* format (Bootstrap 5.2+) for better compatibility with Tabler
        $badgeClasses = [
            'draft' => 'text-bg-secondary',
            'new' => 'text-bg-primary',
            'pending' => 'text-bg-warning',
            'working' => 'text-bg-warning',
            'done' => 'text-bg-success',
            'finished' => 'text-bg-success',
            'ready' => 'text-bg-success text-white',
        ];
        
        // Default to secondary if status not found
        $badgeClass = $badgeClasses[$status] ?? 'text-bg-secondary';
        
        // Add padding and font size classes to make badge bigger
        return '<span class="badge ' . $badgeClass . ' px-2 py-2 fs-4">' . htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('product_type_ge')) {
    /**
     * Translate product type to Georgian
     *
     * @param string $productType The product type value (mirror, glass, lamix, glass_pkg, service)
     * @return string Georgian translation
     */
    function product_type_ge(string $productType): string
    {
        $productType = strtolower($productType);
        
        $translations = [
            'mirror' => 'სარკე',
            'glass' => 'შუშა',
            'lamix' => 'ლამექსი',
            'glass_pkg' => 'მინაპაკეტი',
            'service' => 'მომსახურება',
        ];
        
        return $translations[$productType] ?? $productType;
    }
}

if (!function_exists('order_type_ge')) {
    /**
     * Translate order type to Georgian
     *
     * @param string $orderType The order type value (retail, wholesale)
     * @return string Georgian translation
     */
    function order_type_ge(string $orderType): string
    {
        $orderType = strtolower($orderType);
        
        $translations = [
            'retail' => 'საცალო',
            'wholesale' => 'საბითუმო',
        ];
        
        return $translations[$orderType] ?? $orderType;
    }
}

if (!function_exists('get_extra_field_labels')) {
    /**
     * Get field labels mapping for service extra fields
     *
     * @return array Array mapping field names to their display labels
     */
    function get_extra_field_labels(): array
    {
        return [
            'antifog_type' => 'Anti Fog Type',
            'quantity' => 'Quantity',
            'perimeter' => 'Perimeter',
            'color' => 'Color',
            'light_type' => 'Light Type',
            'foam_length' => 'Foam Length',
            'tape_length' => 'Tape Length',
            'area' => 'Area',
            'length_cm' => 'Length (cm)',
            'sensor_quantity1' => 'Sensor Quantity',
            'sensor_type' => 'Sensor Type',
            'distance' => 'Distance',
            'description' => 'Description',
            'price_gel' => 'Price (GEL)',
            'piece_id' => 'Piece ID',
            'calculate_price_btn' => 'Calculate Price Button',
        ];
    }
}

if (!function_exists('get_service_extra_fields')) {
    /**
     * Get fields to display and their labels from service's extra_field_names
     *
     * @param \App\Models\Service|array|null $serviceOrFields Service model instance or array of extra_field_names
     * @return array Array with 'fields' (array of field names) and 'labels' (array of field => label)
     */
    function get_service_extra_fields($serviceOrFields): array
    {
        // Get field labels mapping
        $allFieldLabels = get_extra_field_labels();
        
        // Extract extra_field_names from service or use provided array
        if (is_object($serviceOrFields) && isset($serviceOrFields->extra_field_names)) {
            $fieldsToDisplay = $serviceOrFields->extra_field_names ?? [];
        } elseif (is_array($serviceOrFields)) {
            $fieldsToDisplay = $serviceOrFields;
        } else {
            $fieldsToDisplay = [];
        }
        
        // Ensure it's an array
        if (!is_array($fieldsToDisplay)) {
            $fieldsToDisplay = [];
        }
        
        // Build labels array for the fields to display
        $labels = [];
        foreach ($fieldsToDisplay as $field) {
            $labels[$field] = $allFieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        }
        
        return [
            'fields' => $fieldsToDisplay,
            'labels' => $labels,
        ];
    }
}


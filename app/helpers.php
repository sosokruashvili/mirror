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
            'working' => 'text-bg-info',
            'done' => 'text-bg-success',
            'finished' => 'text-bg-dark',
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


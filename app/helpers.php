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
            'cut' => 'text-bg-warning text-dark',
            'processed' => 'text-bg-warning',
            'ready' => 'text-bg-primary',
            'broken' => 'text-bg-danger',
        ];
        
        // Default to secondary if status not found
        $badgeClass = $badgeClasses[$status] ?? 'text-bg-secondary';
        
        if ($status === 'processed') {
            return '<span class="badge text-white px-2 py-2 fs-4" style="background-color:#fd7e14;">' . htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        
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

if (!function_exists('piece_stages')) {
    /**
     * Ordered list of production stages a piece goes through.
     *
     * Keyed by slug => Georgian label. The first six slugs intentionally match
     * the production permissions (cutting, processing, …, curing) so a user's
     * capability lines up with the stage they handle; the final stage is
     * 'completion' (დასრულება).
     *
     * @return array<string, string>
     */
    function piece_stages(): array
    {
        return [
            'cutting' => 'მოჭრა',
            'processing' => 'დამუშავება',
            'cutting-drilling' => 'ჭრა/ხვრეტა',
            'assembly' => 'აწყობა',
            'tempering' => 'წრთობა',
            'curing' => 'დამატოვება',
            'completion' => 'დასრულება',
        ];
    }
}

if (!function_exists('piece_stage_colors')) {
    /**
     * Brand color for each production stage, keyed by slug => hex.
     *
     * Progression: cool -> warm -> green (completion). Used to tint stage
     * labels in the team page piece context menu and stage badges.
     *
     * @return array<string, string>
     */
    function piece_stage_colors(): array
    {
        return [
            'cutting' => '#FACC15',
            'processing' => '#0EA5E9',
            'cutting-drilling' => '#6366F1',
            'assembly' => '#F59E0B',
            'tempering' => '#EF4444',
            'curing' => '#7E22CE',
            'completion' => '#10B981',
        ];
    }
}

if (!function_exists('piece_stage_color')) {
    /**
     * Return the hex color for a single stage slug.
     *
     * @param string|null $stage
     * @return string Empty string when the stage is not set/known.
     */
    function piece_stage_color(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return '';
        }

        return piece_stage_colors()[$stage] ?? '';
    }
}

if (!function_exists('piece_stage_text_color')) {
    /**
     * Readable text color to place on top of a stage's background color.
     *
     * Light stage backgrounds (e.g. amber "assembly") get dark text; the rest
     * use white.
     *
     * @param string|null $stage
     * @return string Empty string when the stage is not set/known.
     */
    function piece_stage_text_color(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return '';
        }

        $darkTextStages = ['cutting', 'assembly'];

        return in_array($stage, $darkTextStages, true) ? '#212529' : '#ffffff';
    }
}

if (!function_exists('piece_draft_color')) {
    /**
     * Background color used for a piece/size-group with no stage set yet
     * (i.e. still "draft"). This is the grey that `cutting` used to use.
     */
    function piece_draft_color(): string
    {
        return '#64748B';
    }
}

if (!function_exists('piece_stage_ge')) {
    /**
     * Translate a piece stage slug to its Georgian label.
     *
     * @param string|null $stage
     * @return string Empty string when the stage is not set.
     */
    function piece_stage_ge(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return '';
        }

        return piece_stages()[$stage] ?? $stage;
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


<?php

if (!function_exists('setting')) {
    /**
     * Read a global application setting by key, cast to its declared type.
     *
     * Settings are managed on the Global Settings admin page. Returns $default
     * when the setting is missing or has no value stored.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Models\Setting::get($key, $default);
    }
}

if (!function_exists('order_due_date_progress')) {
    /**
     * Render a compact due-date progress bar for an order list column.
     *
     * Bar width is days-left on a fixed 10-day scale (10+ days = full, 0 = empty),
     * so proportions are comparable across rows. Colors: green (≥5), yellow (3–4),
     * red (≤2 or overdue). Grey when no due date is set.
     *
     * @param \App\Models\Order $order
     * @return string HTML
     */
    function order_due_date_progress($order): string
    {
        if (!$order->due_date) {
            return '<div class="d-flex align-items-center" style="min-width: 110px;" title="'
                . e(__('order.no_due_date')) . '">'
                . '<div class="progress flex-grow-1 me-2" style="height: 6px; min-width: 60px;">'
                . '<div class="progress-bar bg-secondary" role="progressbar" style="width: 100%; opacity: 0.4;"></div>'
                . '</div>'
                . '<small class="text-muted">—</small>'
                . '</div>';
        }

        $today = now()->startOfDay();
        $due = $order->due_date->copy()->startOfDay();
        $daysLeft = (int) $today->diffInDays($due, false);

        // Fixed 10-day scale so fill length matches days left consistently.
        $fullBarDays = 10;
        $percent = $daysLeft <= 0
            ? 0
            : (int) min(100, round(($daysLeft / $fullBarDays) * 100));

        if ($daysLeft >= 5) {
            $barClass = 'bg-success';
        } elseif ($daysLeft >= 3) {
            $barClass = 'bg-warning';
        } else {
            $barClass = 'bg-danger order-due-urgent-bar';
        }

        if ($daysLeft < 0) {
            $label = __('order.overdue_days', ['days' => abs($daysLeft)]);
        } elseif ($daysLeft === 0) {
            $label = __('order.due_today');
        } else {
            $label = __('order.days_left', ['days' => $daysLeft]);
        }

        $title = __('order.due_date') . ': ' . $due->format('Y-m-d') . ' — ' . $label;

        $isUrgent = $daysLeft <= 2;

        // Keep a visible segment so the blink remains noticeable on 1–2 day bars.
        $barStyle = 'width: ' . $percent . '%;';
        if ($isUrgent) {
            $barStyle .= ' min-width: ' . ($percent > 0 ? '12px' : '100%') . ';';
        }

        return sprintf(
            '<div class="d-flex align-items-center" style="min-width: 110px;" title="%s">'
                . '<div class="progress flex-grow-1 me-2" style="height: 6px; min-width: 60px;">'
                    . '<div class="progress-bar %s" role="progressbar" style="%s"></div>'
                . '</div>'
                . '<small class="%s">%s</small>'
            . '</div>',
            e($title),
            e($barClass),
            e($barStyle),
            $isUrgent ? 'text-danger' : 'text-muted',
            e($label)
        );
    }
}

if (!function_exists('order_due_date_dot')) {
    /**
     * Colored urgency dot for an order due date on preview pages.
     *
     * Green ≥5 days, yellow 3–4, red ≤2 or overdue (blinks). Empty when no due date.
     *
     * @param \App\Models\Order $order
     * @return string HTML
     */
    function order_due_date_dot($order): string
    {
        if (!$order->due_date) {
            return '';
        }

        $today = now()->startOfDay();
        $due = $order->due_date->copy()->startOfDay();
        $daysLeft = (int) $today->diffInDays($due, false);

        if ($daysLeft >= 5) {
            $class = 'order-due-dot-success';
        } elseif ($daysLeft >= 3) {
            $class = 'order-due-dot-warning';
        } else {
            $class = 'order-due-dot-danger order-due-dot-urgent urgent-red-pulse';
        }

        return '<span class="order-due-dot ' . $class . '" aria-hidden="true"></span>';
    }
}

if (!function_exists('status_badge')) {
    /**
     * Generate a Bootstrap badge for order status
     *
     * The label is read from the status lang file so it follows the active
     * locale; unknown statuses fall back to the capitalized raw value.
     *
     * @param string $status The status value (draft, new, pending, working, done, finished)
     * @param string|null $text Optional custom text to display instead of the translated status
     * @return string HTML badge element
     */
    function status_badge(string $status, ?string $text = null): string
    {
        $status = strtolower($status);
        $key = "status.{$status}";
        $translated = __($key);
        $displayText = $text ?? ($translated === $key ? ucfirst($status) : $translated);
        
        // Define badge classes for each status using Bootstrap 5/Tabler compatible classes
        // Using text-bg-* format (Bootstrap 5.2+) for better compatibility with Tabler
        $badgeClasses = [
            'draft' => 'text-bg-secondary',
            'new' => 'bg-purple text-white',
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
     * Product type label in the active locale.
     *
     * Reads lang/{locale}/product_type.php. Kept under its original name so the
     * existing call sites keep working; despite the "_ge" suffix it now returns
     * English when the panel is in English.
     *
     * @param string $productType The product type value (mirror, glass, lamix, glass_pkg, service)
     * @return string Localized label, or the raw value when it is not a known type
     */
    function product_type_ge(string $productType): string
    {
        $productType = strtolower($productType);
        $key = "product_type.{$productType}";
        $label = __($key);

        // __() hands back the key itself when there is no such translation.
        return $label === $key ? $productType : $label;
    }
}

if (!function_exists('piece_stages')) {
    /**
     * Ordered list of production stages a piece goes through, keyed by
     * name (slug) => title (Georgian label).
     *
     * Stages are managed via the Stage CRUD and ordered by their `position`
     * column. The `name` slug is what gets stored on `pieces.stage`; the final
     * stage 'completion' (დასრულება) still drives the order "ready" status.
     *
     * @return array<string, string>
     */
    function piece_stages(): array
    {
        return \App\Models\Stage::ordered()->pluck('title', 'name')->all();
    }
}

if (!function_exists('piece_universal_stages')) {
    /**
     * Stages that apply to every piece regardless of its services (e.g. მოჭრა,
     * დასრულება), keyed by name (slug) => title, in production order.
     *
     * A piece's selectable stages are these universal stages plus the stages of
     * the services attached to it.
     *
     * @return array<string, string>
     */
    function piece_universal_stages(): array
    {
        return \App\Models\Stage::ordered()
            ->where('is_universal', true)
            ->pluck('title', 'name')
            ->all();
    }
}

if (!function_exists('piece_stage_colors')) {
    /**
     * Badge color for each production stage, keyed by name (slug) => hex.
     *
     * Used to tint stage labels in the team page piece context menu and stage
     * badges. Managed via the Stage CRUD (color picker).
     *
     * @return array<string, string>
     */
    function piece_stage_colors(): array
    {
        return \App\Models\Stage::ordered()->pluck('color', 'name')->all();
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

if (!function_exists('stage_contrast_text_color')) {
    /**
     * Readable text color (dark or white) for a given background hex color,
     * chosen by the background's perceived luminance.
     *
     * @param string|null $hex Background color, e.g. "#F59E0B".
     * @return string '#212529' for light backgrounds, '#ffffff' for dark ones.
     */
    function stage_contrast_text_color(?string $hex): string
    {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Perceived luminance (ITU-R BT.601). Light backgrounds get dark text.
        $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

        return $luminance > 150 ? '#212529' : '#ffffff';
    }
}

if (!function_exists('piece_stage_text_color')) {
    /**
     * Readable text color to place on top of a stage's background color.
     * Derived from the stage's own color so admin-picked colors stay readable.
     *
     * @param string|null $stage
     * @return string Empty string when the stage is not set/known.
     */
    function piece_stage_text_color(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return '';
        }

        $color = piece_stage_color($stage);

        if ($color === '') {
            return '';
        }

        return stage_contrast_text_color($color);
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
     * Order type label in the active locale.
     *
     * Reads the order_type lang file. Kept under its original name so existing
     * call sites keep working; despite the "_ge" suffix it now returns English
     * when the panel is in English.
     *
     * @param string $orderType The order type value (retail, wholesale)
     * @return string Localized label, or the raw value when it is not a known type
     */
    function order_type_ge(string $orderType): string
    {
        $orderType = strtolower($orderType);
        $key = "order_type.{$orderType}";
        $label = __($key);

        // __() hands back the key itself when there is no such translation.
        return $label === $key ? $orderType : $label;
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

if (!function_exists('order_service_measure')) {
    /**
     * Resolve the billable measure of an order_service pivot row.
     *
     * Which pivot column holds the measure depends on the service: perimeter for
     * frames/edges, area for matting, length_cm for cutouts, and so on. The
     * services.unit column does not reliably describe it (e.g. ევრო კრომკა is
     * billed per perimeter metre but carries unit "ცალი"), so the unit is taken
     * from the resolved column instead.
     *
     * @param \App\Models\Service $service Service with its order pivot loaded
     * @return array{field: ?string, qty: ?float, unit: ?string}
     */
    function order_service_measure($service): array
    {
        // Column => unit, in the order they should be preferred.
        $measures = [
            'area' => 'კვ.მ',
            'perimeter' => 'მ',
            'length_cm' => 'სმ',
            'distance' => 'კმ',
            'tape_length' => 'მ',
            'foam_length' => 'მ',
            'sensor_quantity1' => 'ცალი',
            'quantity' => 'ცალი',
        ];

        $enabled = $service->extra_field_names ?? [];
        if (!is_array($enabled)) {
            $enabled = [];
        }

        $pivot = $service->pivot ?? null;
        $none = ['field' => null, 'qty' => null, 'unit' => null];

        if (!$pivot) {
            return $none;
        }

        foreach ($measures as $field => $unit) {
            if (!in_array($field, $enabled, true)) {
                continue;
            }

            $value = $pivot->{$field} ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            return ['field' => $field, 'qty' => (float) $value, 'unit' => $unit];
        }

        return $none;
    }
}


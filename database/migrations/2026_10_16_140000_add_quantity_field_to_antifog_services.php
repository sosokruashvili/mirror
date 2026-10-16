<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Antifog is billed per piece. The quantity typed on the order line is the
     * total, so the field has to be on the form.
     */
    public function up(): void
    {
        Service::query()->where('slug', 'antifog')->orderBy('id')->each(function (Service $service) {
            $fields = $service->extra_field_names ?? [];
            if (! is_array($fields) || in_array('quantity', $fields, true)) {
                return;
            }

            array_unshift($fields, 'quantity');
            $service->extra_field_names = array_values($fields);
            $service->save();
        });
    }

    public function down(): void
    {
        Service::query()->where('slug', 'antifog')->orderBy('id')->each(function (Service $service) {
            $fields = $service->extra_field_names ?? [];
            if (! is_array($fields) || ! in_array('sensor_quantity1', $fields, true)) {
                return;
            }

            $service->extra_field_names = array_values(array_filter(
                $fields,
                fn ($field) => $field !== 'quantity'
            ));
            $service->save();
        });
    }
};

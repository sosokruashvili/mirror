<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Switch for the automatic order_ready SMS (OrderReadySms). Starts off:
     * the text is still being written.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'order_ready_sms',
            'value' => '0',
            'type' => 'boolean',
            'label' => 'Order ready SMS',
            'description' => 'Send the client an SMS (template order_ready) when an order becomes ready.',
            'group' => 'SMS',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::forgetCache();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'order_ready_sms')->delete();

        Setting::forgetCache();
    }
};

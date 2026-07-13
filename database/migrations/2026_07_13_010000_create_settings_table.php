<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global application settings, edited via the Global Settings page.
     *
     * Each row is one parameter: `key` is the machine identifier read through
     * the setting() helper; `value` stores the (stringified) value; `type`
     * drives how the settings form renders and validates the input (e.g.
     * integer); `label`/`description` are shown on the form; `group` and
     * `position` control grouping/ordering on the page.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text');
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('group')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'key' => 'cutting_size',
            'value' => null,
            'type' => 'integer',
            'label' => 'Cutting Size',
            'description' => 'Cutting size in millimetres (mm).',
            'group' => 'Production',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

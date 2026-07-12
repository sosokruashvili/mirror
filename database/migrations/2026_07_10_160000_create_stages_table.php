<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production stages a piece goes through, now editable via CRUD instead of
     * living in static helpers. `name` is the machine identifier stored on
     * `pieces.stage` (and referenced by order-status logic, e.g. 'completion');
     * `title` is the Georgian display label; `color` is the badge hex color;
     * `position` controls the ordering used everywhere stages are listed.
     */
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->string('color')->default('#64748b');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Seed the stages that used to live in the piece_stages() helper so the
        // switch to CRUD is transparent for existing pieces (pieces.stage keeps
        // referencing these `name` slugs).
        $now = now();
        $stages = [
            ['name' => 'cutting', 'title' => 'მოჭრა', 'color' => '#facc15'],
            ['name' => 'processing', 'title' => 'დამუშავება', 'color' => '#0ea5e9'],
            ['name' => 'cutting-drilling', 'title' => 'ჭრა/ხვრეტა', 'color' => '#6366f1'],
            ['name' => 'tempering', 'title' => 'წრთობა', 'color' => '#ef4444'],
            ['name' => 'assembly', 'title' => 'აწყობა', 'color' => '#f59e0b'],
            ['name' => 'curing', 'title' => 'დამატოვება', 'color' => '#7e22ce'],
            ['name' => 'completion', 'title' => 'დასრულება', 'color' => '#10b981'],
        ];

        foreach ($stages as $index => $stage) {
            DB::table('stages')->insert(array_merge($stage, [
                'position' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};

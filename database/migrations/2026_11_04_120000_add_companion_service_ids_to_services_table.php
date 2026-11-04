<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * განათება თბილი / ცივი / ნეიტრალური always ship with ქაფი, ასაწყობი მასალა
     * and საკიდი. Staff used to add all four lines by hand; now picking the light
     * on the order form adds the other three as separate lines, so service stats
     * still count each one on its own.
     */
    private const LIGHTS = [60, 62, 63];

    /** ქაფი, ასაწყობი მასალა, საკიდი — in the order they are added to the form. */
    private const COMPANIONS = [53, 56, 14];

    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('companion_service_ids')->nullable()->after('extra_field_names');
        });

        $existing = DB::table('services')->whereIn('id', self::COMPANIONS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $companions = array_values(array_filter(self::COMPANIONS, fn (int $id) => in_array($id, $existing, true)));

        if ($companions !== []) {
            DB::table('services')
                ->whereIn('id', self::LIGHTS)
                ->update(['companion_service_ids' => json_encode($companions)]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('companion_service_ids');
        });
    }
};

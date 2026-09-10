<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Lets a limited-time plan (e.g. "September 2-in-1 Promo") force every
        // booking under it to end on a fixed date, regardless of the duration
        // picked — configurable from the DB instead of hardcoded in code.
        Schema::table('plans', function (Blueprint $table) {
            $table->date('promo_fixed_end_date')->nullable()->after('is_automatic_daily');
        });
    }
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('promo_fixed_end_date');
        });
    }
};

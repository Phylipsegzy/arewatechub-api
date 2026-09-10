<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Lets a plan restrict itself to one day of the week — e.g. "Free
        // Access Wednesday" only being bookable on an actual Wednesday.
        // Stores Carbon's dayOfWeek integer (0 = Sunday ... 6 = Saturday).
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('restricted_weekday')->nullable()->after('promo_fixed_end_date');
        });
    }
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('restricted_weekday');
        });
    }
};

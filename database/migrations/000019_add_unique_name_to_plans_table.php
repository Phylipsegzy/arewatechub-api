<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Prevents the exact bug that caused "Basic Package" and "September
        // 2-in-1 Promo" to show twice — re-running the seeder can no longer
        // create a second plan with the same name; the seeder's
        // firstOrCreate() will just find and reuse the existing one instead.
        //
        // IMPORTANT: if you already have duplicate plan names in your DB,
        // this migration will fail with a duplicate-key error. Run
        // `php artisan plans:dedupe` FIRST, then run this migration.
        Schema::table('plans', function (Blueprint $table) {
            $table->unique('name');
        });
    }
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};

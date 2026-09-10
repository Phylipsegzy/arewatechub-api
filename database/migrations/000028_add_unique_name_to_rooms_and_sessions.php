<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Same fix as the plans.name unique constraint — prevents rooms and
        // sessions from ever being duplicated again by a re-run seeder.
        //
        // IMPORTANT: if you still have duplicate room or session names,
        // this migration will fail with a duplicate-key error. Run
        // `php artisan workspace:dedupe` FIRST, then run this migration.
        Schema::table('rooms', function (Blueprint $table) {
            $table->unique('name');
        });
        Schema::table('workspace_sessions', function (Blueprint $table) {
            $table->unique('name');
        });
    }
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
        Schema::table('workspace_sessions', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};

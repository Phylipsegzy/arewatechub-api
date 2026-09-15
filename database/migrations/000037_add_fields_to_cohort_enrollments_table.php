<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cohort_enrollments', function (Blueprint $table) {
            $table->string('programme_selected')->nullable()->after('track_selected');
            $table->enum('bootcamp_option', ['bootcamp', 'non_bootcamp'])->nullable()->after('programme_selected');
            $table->enum('status_type', ['Corp Member', 'Student', 'Working Class'])->nullable()->after('bootcamp_option');
            $table->string('matric_number')->nullable()->after('state_code');
            $table->string('reference')->nullable()->unique()->after('amount_paid');
        });
    }
    public function down(): void
    {
        Schema::table('cohort_enrollments', function (Blueprint $table) {
            $table->dropColumn(['programme_selected', 'bootcamp_option', 'status_type', 'matric_number', 'reference']);
        });
    }
};

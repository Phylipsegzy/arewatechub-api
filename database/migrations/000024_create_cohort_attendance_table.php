<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cohort_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_session_id')->constrained('cohort_sessions')->cascadeOnDelete();
            $table->foreignId('cohort_enrollment_id')->constrained('cohort_enrollments')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['cohort_session_id', 'cohort_enrollment_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('cohort_attendance'); }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A single scheduled live class within an intake (Week 1 Day 1, etc.)
        Schema::create('cohort_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_intake_id')->constrained('cohort_intakes')->cascadeOnDelete();
            $table->string('title'); // "Week 1: Intro to HTML/CSS"
            $table->unsignedInteger('week_no')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('google_meet_link')->nullable();
            $table->string('instructor_name')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cohort_sessions'); }
};

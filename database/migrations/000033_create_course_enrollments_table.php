<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Created once payment succeeds (via orders table). No admission step.
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('certificate_file')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'customer_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('course_enrollments'); }
};

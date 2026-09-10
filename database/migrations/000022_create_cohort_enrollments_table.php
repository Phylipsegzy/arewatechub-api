<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Application + admission + payment status for a customer joining an intake
        Schema::create('cohort_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_intake_id')->constrained('cohort_intakes')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('track_selected')->nullable();   // e.g. "Digital Marketing"
            $table->string('tuition_tier')->nullable();     // early_bird, regular...
            $table->decimal('bootcamp_fee', 12, 2)->default(0);
            $table->decimal('tuition_fee', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('application_status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('motivation')->nullable();
            $table->string('state_code')->nullable();
            $table->string('education_level')->nullable();
            $table->boolean('has_laptop')->default(false);
            $table->enum('certificate_status', ['not_ready', 'ready'])->default('not_ready');
            $table->string('certificate_file')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cohort_enrollments'); }
};

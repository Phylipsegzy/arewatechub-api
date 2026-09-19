<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fresh rebuild under new table/route names, after the cohort_enrollments
// endpoint got stuck in an unexplained broken state despite exhaustive
// diagnosis (route caching turned out to be a real, fixed bug, but this
// specific table/path combination stayed broken regardless). The old
// tables were confirmed completely empty throughout — nothing is lost by
// dropping them.
return new class extends Migration {
    public function up(): void
    {
        // Correct dependency order — children before parents:
        // cohort_attendance -> cohort_sessions -> cohort_enrollments -> cohort_intakes -> cohort_programs
        Schema::dropIfExists('cohort_attendance');
        Schema::dropIfExists('cohort_sessions');
        Schema::dropIfExists('cohort_enrollments');
        Schema::dropIfExists('cohort_intakes');
        Schema::dropIfExists('cohort_programs');

        Schema::create('academy_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academy_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('capacity')->default(30);
            $table->timestamps();
        });

        Schema::create('academy_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('track_selected');
            $table->string('programme_selected');
            $table->enum('bootcamp_option', ['bootcamp', 'non_bootcamp']);
            $table->enum('status_type', ['Corp Member', 'Student', 'Working Class']);
            $table->string('tuition_tier')->nullable();
            $table->decimal('bootcamp_fee', 10, 2);
            $table->decimal('tuition_fee', 10, 2);
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('reference')->nullable()->unique();
            $table->enum('application_status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('motivation')->nullable();
            $table->string('state_code')->nullable();
            $table->string('matric_number')->nullable();
            $table->string('education_level')->nullable();
            $table->boolean('has_laptop')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_enrollments');
        Schema::dropIfExists('academy_batches');
        Schema::dropIfExists('academy_programs');
    }
};

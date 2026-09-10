<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A batch/intake within a program (Batch A, Batch B... with dates + capacity)
        Schema::create('cohort_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_program_id')->constrained('cohort_programs')->cascadeOnDelete();
            $table->string('name'); // "Batch A"
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('capacity')->default(20);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cohort_intakes'); }
};

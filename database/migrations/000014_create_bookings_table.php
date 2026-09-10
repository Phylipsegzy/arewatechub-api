<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->foreignId('plan_duration_id')->nullable()->constrained('plan_durations')->nullOnDelete();
            $table->foreignId('workspace_session_id')->nullable()->constrained('workspace_sessions')->nullOnDelete();
            $table->foreignId('room_id')->constrained('rooms');
            $table->unsignedInteger('seat_number');
            $table->decimal('price', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->date('start_date');
            $table->date('end_date');
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('end_datetime')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bookings'); }
};

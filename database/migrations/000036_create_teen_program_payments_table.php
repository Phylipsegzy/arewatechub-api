<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teen_program_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('teen_program_registrations')->cascadeOnDelete();
            $table->enum('payment_type', ['registration', 'vip']);
            $table->decimal('amount', 10, 2);
            $table->string('reference')->unique();
            $table->string('status')->default('Success');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('teen_program_payments'); }
};

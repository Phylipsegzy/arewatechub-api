<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('source'); // paystack_funding | bank_transfer | booking_payment | course_payment | cohort_payment | refund
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
            $table->string('proof_of_payment')->nullable(); // manual bank transfer proof
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('wallet_transactions'); }
};

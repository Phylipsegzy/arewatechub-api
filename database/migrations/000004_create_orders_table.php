<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Shared checkout/payment record for ANY purchasable item:
        // a workspace booking, a self-paced course, or a cohort registration.
        // orderable_type/orderable_id = polymorphic link (Laravel morphs).
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->morphs('orderable'); // orderable_type, orderable_id
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['paystack', 'wallet', 'bank_transfer']);
            $table->string('reference')->unique();
            $table->string('paystack_reference')->nullable();
            $table->enum('status', ['initialized', 'pending', 'success', 'failed'])->default('initialized');
            $table->string('proof_of_payment')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Gen Alpha Future Builders Camp — one row per child. Ported from
        // the legacy procedural site's final pricing model: a compulsory
        // ₦15,000 registration/acceptance fee that secures the slot, plus
        // an optional ₦250,000 VIP upgrade addable any time afterward
        // (not a choice made at registration time).
        Schema::create('teen_program_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('child_firstname');
            $table->string('child_lastname');
            $table->unsignedTinyInteger('child_age');
            $table->enum('child_gender', ['Male', 'Female'])->nullable();
            $table->string('school')->nullable();
            $table->string('parent_name');
            $table->string('parent_phone');
            $table->string('parent_address');
            $table->string('nearest_landmark');
            $table->string('relationship')->nullable(); // Mother/Father/Guardian
            $table->decimal('registration_amount', 10, 2)->default(15000);
            $table->enum('registration_payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->boolean('vip_requested')->default(false);
            $table->decimal('vip_amount', 10, 2)->default(250000);
            $table->enum('vip_payment_status', ['not_requested', 'unpaid', 'paid'])->default('not_requested');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('teen_program_registrations'); }
};

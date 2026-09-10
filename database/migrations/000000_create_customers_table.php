<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('middlename')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('nin')->nullable();
            $table->string('picture_url')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->date('dob')->nullable();
            $table->string('education')->nullable();
            $table->string('password');
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->enum('paystack_status', ['pending', 'created', 'failed'])->default('pending');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('customers'); }
};

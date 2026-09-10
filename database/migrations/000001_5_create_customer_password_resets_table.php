<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token'); // sha256 hash of the token emailed to the customer
            $table->dateTime('expires_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('customer_password_resets'); }
};

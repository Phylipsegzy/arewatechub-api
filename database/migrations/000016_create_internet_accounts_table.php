<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pool of WiFi/internet login credentials your team hands out with a
        // workspace booking. Admin adds these via the admin panel; the app
        // assigns one automatically when a booking is confirmed.
        Schema::create('internet_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('password');
            $table->enum('duration_type', ['daily', 'weekly', 'monthly']);
            $table->enum('status', ['available', 'assigned', 'inactive'])->default('available');
            $table->date('last_assigned')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('internet_accounts'); }
};

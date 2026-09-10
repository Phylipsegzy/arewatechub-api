<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('paystack');
            $table->string('mode')->default('test'); // test | live
            $table->text('secret_key');
            $table->text('public_key');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('api_keys'); }
};

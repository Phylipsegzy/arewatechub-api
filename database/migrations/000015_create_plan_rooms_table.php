<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Which rooms are actually bookable under a given plan (matches your live plan_rooms table).
        Schema::create('plan_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['plan_id', 'room_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('plan_rooms'); }
};

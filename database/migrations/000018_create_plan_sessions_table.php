<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Which time-slot sessions a customer can pick from under a given
        // plan. Most plans allow all three; "Free Access Wednesday" only
        // allows the 9am-3pm session — data-driven here rather than
        // hardcoded, so that rule (or future ones like it) lives in the DB.
        Schema::create('plan_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('workspace_session_id')->constrained('workspace_sessions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['plan_id', 'workspace_session_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('plan_sessions'); }
};

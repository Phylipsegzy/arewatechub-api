<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Morning, Afternoon, Night, Whole Day...
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('workspace_sessions'); }
};

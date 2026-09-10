<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Self-paced course catalog item. No dates/schedule — buy anytime.
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('courses'); }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('name')->nullable()->after('email');
            $table->enum('role', ['admin', 'cashier'])->default('admin')->after('name');
            $table->foreignId('created_by')->nullable()->after('role')->constrained('admins')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['name', 'role']);
        });
    }
};

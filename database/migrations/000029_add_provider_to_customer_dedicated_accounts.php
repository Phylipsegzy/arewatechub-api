<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A customer can hold one dedicated account per provider (Wema Bank
        // AND Paystack-Titan simultaneously, both crediting the same
        // wallet) — this tracks which provider was requested so we don't
        // create a second account under the same one, while still allowing
        // one of each.
        Schema::table('customer_dedicated_accounts', function (Blueprint $table) {
            $table->string('provider')->default('wema-bank')->after('customer_id');
            $table->unique(['customer_id', 'provider']);
        });
    }
    public function down(): void
    {
        Schema::table('customer_dedicated_accounts', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'provider']);
            $table->dropColumn('provider');
        });
    }
};

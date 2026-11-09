<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // bank | jazzcash | easypaisa | cod
            $table->string('payment_method')->default('bank')->after('payment_status');
            $table->string('payer_name')->nullable()->after('payment_reference');
            $table->decimal('payment_fee', 10, 2)->default(0)->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payer_name', 'payment_fee']);
        });
    }
};

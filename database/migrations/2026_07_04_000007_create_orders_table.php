<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // pending | processing | shipped | delivered | cancelled
            $table->string('status')->default('pending');
            // submitted | verified | rejected
            $table->string('payment_status')->default('submitted');

            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_screenshot')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_note')->nullable();
            $table->timestamp('payment_verified_at')->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            $table->string('ship_name');
            $table->string('ship_phone');
            $table->string('ship_address');
            $table->string('ship_city');
            $table->string('ship_state')->nullable();
            $table->string('ship_postal')->nullable();
            $table->string('ship_country');
            $table->text('order_note')->nullable();

            $table->string('tracking_number')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Base PKR rates for the live rates board; the page moves them a little each tick.
        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->string('metal'); // gold | silver
            $table->string('label'); // "1 Tola", "10 Gram", "1 KG"
            $table->string('note')->nullable(); // "QR Packaging", "999", "Bar"
            $table->decimal('buy', 12, 2);
            $table->decimal('sell', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_rates');
    }
};

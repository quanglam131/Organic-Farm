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
            $table->string('code')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, paid, shipping, delivered, cancelled
            $table->string('payment_method', 20)->default('cod'); // cod, vnpay
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, paid, failed
            $table->string('payment_ref')->nullable()->unique();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('points_discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedInteger('points_used')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedSmallInteger('distance_km')->default(0);
            $table->string('shipping_name');
            $table->string('shipping_phone', 20);
            $table->string('shipping_address');
            $table->date('delivery_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

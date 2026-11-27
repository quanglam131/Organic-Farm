<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A monthly package: the system auto-creates one order per week on
        // `delivery_weekday` (0 = Sunday ... 6 = Saturday) until `ends_on`.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active'); // active, paused, cancelled, completed
            $table->unsignedTinyInteger('delivery_weekday');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('next_order_on')->index();
            $table->string('shipping_name');
            $table->string('shipping_phone', 20);
            $table->string('shipping_address');
            $table->string('payment_method', 20)->default('cod');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepaid monthly subscriptions (Tuần 2, Tuần 3): the customer pays for one month up front and
 * chooses one or more delivery weekdays (e.g. Tuesday and Friday). Every delivery date is stored
 * in delivery_schedules, and an order is generated from each row when its date comes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('delivery_days')->nullable()->after('delivery_weekday');
            $table->unsignedBigInteger('total_price')->default(0)->after('payment_method');
            $table->string('payment_status', 20)->default('unpaid')->after('total_price');
            $table->string('payment_ref')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_ref');
        });

        Schema::create('delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('delivery_date');
            // scheduled -> ordered, or short (not enough stock: Sales must contact the customer)
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'delivery_date']);
            $table->index(['delivery_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_schedules');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['delivery_days', 'total_price', 'payment_status', 'payment_ref', 'paid_at']);
        });
    }
};

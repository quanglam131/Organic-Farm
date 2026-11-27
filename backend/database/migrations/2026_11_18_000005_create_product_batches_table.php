<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A batch (lô) is the unit of stock. Each batch has its own expiry date
        // and its own traceability QR code (trace_code).
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_plot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_code')->unique();
            $table->string('trace_code', 32)->unique();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_on_hand');
            $table->date('harvested_on');
            $table->date('expires_on');
            $table->string('status', 20)->default('available'); // available, depleted, expired, written_off
            $table->timestamps();

            $table->index(['product_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};

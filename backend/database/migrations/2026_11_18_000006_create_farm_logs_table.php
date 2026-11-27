<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // planting, watering, fertilizing, pest_control, harvesting, other
            $table->text('description');
            $table->date('logged_on');
            $table->string('media_url')->nullable();
            $table->string('media_type', 10)->nullable(); // image | video
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['farm_plot_id', 'logged_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_logs');
    }
};

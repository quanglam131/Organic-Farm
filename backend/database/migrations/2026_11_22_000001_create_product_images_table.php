<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Images live in their own table so a product can have many photos that are
        // added, reordered, replaced or removed independently of the product record.
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        // Move existing single images into the new table as primary images.
        $now = now();
        DB::table('products')
            ->whereNotNull('image_url')
            ->get(['id', 'name', 'image_url'])
            ->each(function ($product) use ($now) {
                DB::table('product_images')->insert([
                    'product_id' => $product->id,
                    'url' => $product->image_url,
                    'alt' => $product->name,
                    'sort_order' => 0,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};

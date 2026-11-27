<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test batches (lô) for the FEFO algorithm. Use the states to create the cases the
 * assignment asks for: near expiry, already expired, and zero stock.
 *
 * @extends Factory<ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    public function definition(): array
    {
        $received = fake()->numberBetween(5, 30);

        return [
            'product_id' => Product::factory(),
            'farm_plot_id' => null,
            'batch_code' => 'LO-'.strtoupper(Str::random(8)),
            'trace_code' => 'TR'.strtoupper(Str::random(10)),
            'quantity_received' => $received,
            'quantity_on_hand' => $received,
            'harvested_on' => now()->subDays(2)->toDateString(),
            'expires_on' => now()->addDays(10)->toDateString(),
            'status' => ProductBatch::STATUS_AVAILABLE,
        ];
    }

    /** Stock that expires in the given number of days (must be at least 1 to be sellable). */
    public function expiringIn(int $days, int $quantity): static
    {
        return $this->state(fn () => [
            'expires_on' => now()->addDays($days)->toDateString(),
            'quantity_received' => $quantity,
            'quantity_on_hand' => $quantity,
        ]);
    }

    /** Stock already past its expiry date. */
    public function expired(int $quantity = 5): static
    {
        return $this->state(fn () => [
            'expires_on' => now()->subDay()->toDateString(),
            'quantity_received' => $quantity,
            'quantity_on_hand' => $quantity,
            'status' => ProductBatch::STATUS_EXPIRED,
        ]);
    }

    /** A batch whose stock has been sold out. */
    public function depleted(): static
    {
        return $this->state(fn () => [
            'quantity_on_hand' => 0,
            'status' => ProductBatch::STATUS_DEPLETED,
        ]);
    }
}

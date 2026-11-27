<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Seeder;

/**
 * Test data for the FEFO (First-Expired, First-Out) algorithm.
 *
 * Creates one hidden product whose batches cover every case the algorithm must handle:
 *   - A: 3 kg, expires in 2 days   (near expiry, sold first)
 *   - B: 7 kg, expires in 9 days   (sold second)
 *   - C: 5 kg, expired yesterday   (must never be sold)
 *   - D: 0 kg, expires in 5 days   (zero stock, must be skipped)
 *
 * So buying 10 kg takes 3 kg from A and 7 kg from B, and buying more than 10 kg fails.
 * Hidden from the shop (is_active = false). Safe to run more than once.
 *
 *   php artisan db:seed --class=FefoTestSeeder
 */
class FefoTestSeeder extends Seeder
{
    public const SLUG = 'ca-chua-fefo-test';

    public function run(): void
    {
        if (Product::where('slug', self::SLUG)->exists()) {
            $this->command?->info('FEFO test product already exists.');

            return;
        }

        $product = Product::factory()->hidden()->create([
            'name' => 'Cà chua (dữ liệu test FEFO)',
            'slug' => self::SLUG,
            'unit' => 'kg',
            'price' => 45000,
        ]);

        ProductBatch::factory()->for($product)->expiringIn(2, 3)->create(['batch_code' => 'FEFO-A']);
        ProductBatch::factory()->for($product)->expiringIn(9, 7)->create(['batch_code' => 'FEFO-B']);
        ProductBatch::factory()->for($product)->expired(5)->create(['batch_code' => 'FEFO-C']);
        ProductBatch::factory()->for($product)->expiringIn(5, 4)->depleted()->create(['batch_code' => 'FEFO-D']);

        $this->command?->info('Created the FEFO test product with 4 batches.');
    }
}

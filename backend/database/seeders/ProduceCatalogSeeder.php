<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FarmPlot;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Extra vegetables and fruits for the catalogue.
 *
 * Safe to run more than once: a product is created only when its slug does not exist yet,
 * and batches are received only for products created in this run. Existing stock is never duplicated.
 *
 *   php artisan db:seed --class=ProduceCatalogSeeder
 */
class ProduceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $inventory = app(InventoryService::class);

        $categories = [
            'rau-cu' => Category::where('slug', 'rau-cu')->firstOrFail(),
            'trai-cay' => Category::where('slug', 'trai-cay')->firstOrFail(),
        ];
        $plots = [
            'veg' => FarmPlot::where('code', 'LD-01')->firstOrFail(),
            'fruit' => FarmPlot::where('code', 'LD-02')->firstOrFail(),
        ];

        // name, category key, price (VND), unit, plot key, batches as [quantity, days until expiry]
        $catalog = [
            // ----- Rau củ -----
            ['Cà rốt Đà Lạt', 'rau-cu', 30000, 'kg', 'veg', [[35, 10], [20, 16]]],
            ['Bắp cải tím', 'rau-cu', 28000, 'kg', 'veg', [[25, 6]]],
            ['Súp lơ xanh', 'rau-cu', 42000, 'kg', 'veg', [[18, 4], [12, 9]]],
            ['Khoai tây Đà Lạt', 'rau-cu', 26000, 'kg', 'veg', [[60, 25]]],
            ['Hành tây', 'rau-cu', 24000, 'kg', 'veg', [[45, 30]]],
            ['Ớt chuông đỏ', 'rau-cu', 65000, 'kg', 'veg', [[12, 7]]],

            ['Dưa leo', 'rau-cu', 18000, 'kg', 'veg', [[30, 5]]],
            ['Rau muống', 'rau-cu', 12000, 'bó 500g', 'veg', [[50, 2]]],
            ['Cải thìa', 'rau-cu', 25000, 'kg', 'veg', [[22, 4]]],
            ['Mồng tơi', 'rau-cu', 10000, 'bó 300g', 'veg', [[40, 2]]],

            // ----- Trái cây -----
            ['Xoài cát Hòa Lộc', 'trai-cay', 85000, 'kg', 'fruit', [[30, 8], [25, 14]]],
            ['Chuối tiêu', 'trai-cay', 35000, 'kg', 'fruit', [[50, 4]]],
            ['Thanh long ruột đỏ', 'trai-cay', 32000, 'kg', 'fruit', [[40, 9]]],
            ['Nho xanh không hạt', 'trai-cay', 110000, 'kg', 'fruit', [[15, 6]]],

            ['Cam sành', 'trai-cay', 40000, 'kg', 'fruit', [[35, 20]]],
            ['Măng cụt', 'trai-cay', 95000, 'kg', 'fruit', [[10, 6]]],


            ['Dưa hấu không hạt', 'trai-cay', 22000, 'kg', 'fruit', [[45, 6]]],
            ['Chôm chôm', 'trai-cay', 45000, 'kg', 'fruit', [[22, 5]]],
        ];

        $created = 0;

        foreach ($catalog as [$name, $categoryKey, $price, $unit, $plotKey, $batches]) {
            $slug = Str::slug($name);

            if (Product::where('slug', $slug)->exists()) {
                continue;
            }

            $product = Product::create([
                'category_id' => $categories[$categoryKey]->id,
                'name' => $name,
                'slug' => $slug,
                'unit' => $unit,
                'price' => $price,
                'description' => "{$name} tươi từ nông trại, thu hoạch theo đơn và giao trong ngày.",
                'is_active' => true,
            ]);

            foreach ($batches as [$quantity, $daysToExpiry]) {
                $inventory->receive([
                    'product_id' => $product->id,
                    'farm_plot_id' => $plots[$plotKey]->id,
                    'quantity_received' => $quantity,
                    'harvested_on' => now()->subDays(1)->toDateString(),
                    'expires_on' => now()->addDays($daysToExpiry)->toDateString(),
                ], $admin->id);
            }

            $created++;
        }

        $this->command?->info("Added {$created} new product(s).");
    }
}

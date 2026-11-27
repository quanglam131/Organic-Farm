<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Attaches the photos in storage/app/public/anh-san-pham to their products.
 *
 * A file named after the product slug (for example ca-rot-da-lat.jpg) is added to that
 * product's gallery and made its primary photo, which also sets products.image_url.
 * Safe to run more than once: a photo already in the gallery is skipped.
 *
 *   php artisan db:seed --class=ProductPhotoSeeder
 */
class ProductPhotoSeeder extends Seeder
{
    private const FOLDER = 'anh-san-pham';

    public function run(ProductImageService $images): void
    {
        $attached = 0;

        foreach (Storage::disk('public')->files(self::FOLDER) as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);

            $product = Product::where('slug', $slug)->first();
            if ($product === null) {
                $this->command?->warn("No product for {$path}, skipped.");

                continue;
            }

            $url = '/storage/'.$path;
            if (ProductImage::where('product_id', $product->id)->where('url', $url)->exists()) {
                continue;
            }

            $image = $images->add($product, null, $url, $product->name);
            $images->makePrimary($image);
            $attached++;
        }

        $this->command?->info("Attached {$attached} product photo(s).");
    }
}

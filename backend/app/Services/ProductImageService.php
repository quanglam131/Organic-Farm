<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Manages the photos of a product. The primary image is mirrored to products.image_url
 * so product lists and the cart can keep reading a single URL.
 */
class ProductImageService
{
    public function __construct(private MediaStorage $media) {}

    public function add(Product $product, ?UploadedFile $file, ?string $url, ?string $alt = null): ProductImage
    {
        if ($file === null && ($url === null || $url === '')) {
            throw new DomainException('Cần tải lên một ảnh hoặc nhập đường dẫn ảnh.');
        }

        $storedUrl = $file !== null ? $this->media->store($file, 'products') : $url;

        return DB::transaction(function () use ($product, $storedUrl, $alt) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $last = $product->images()->max('sort_order');

            $image = ProductImage::create([
                'product_id' => $product->id,
                'url' => $storedUrl,
                'alt' => $alt ?? $product->name,
                'sort_order' => $last === null ? 0 : $last + 1,
                'is_primary' => false,
            ]);

            // The first photo of a product becomes its primary image automatically.
            if (! ProductImage::where('product_id', $product->id)->where('is_primary', true)->exists()) {
                $this->makePrimary($image);
            }

            return $image->refresh();
        });
    }

    public function makePrimary(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            ProductImage::where('product_id', $image->product_id)->update(['is_primary' => false]);
            // Write straight to the database: $image->update() would skip the query when the
            // model already thinks it is primary, leaving the product with no primary photo.
            ProductImage::whereKey($image->id)->update(['is_primary' => true]);
            $image->setAttribute('is_primary', true)->syncOriginalAttribute('is_primary');
            Product::whereKey($image->product_id)->update(['image_url' => $image->url]);
        });
    }

    /** Deleting the primary image promotes the next photo, or clears the product image. */
    public function delete(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $productId = $image->product_id;
            $wasPrimary = $image->is_primary;

            $image->delete();

            if (! $wasPrimary) {
                return;
            }

            $next = ProductImage::where('product_id', $productId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($next !== null) {
                $this->makePrimary($next);
            } else {
                Product::whereKey($productId)->update(['image_url' => null]);
            }
        });
    }
}

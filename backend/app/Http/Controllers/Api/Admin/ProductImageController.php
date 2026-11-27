<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Photo management for one product: list, add (upload or URL), reorder, set primary, delete. */
class ProductImageController extends Controller
{
    public function __construct(private ProductImageService $images) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json($product->images);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'max:4096', 'required_without:url'],
            'url' => ['nullable', 'url', 'max:500', 'required_without:image'],
            'alt' => ['nullable', 'string', 'max:150'],
        ]);

        $image = $this->images->add(
            $product,
            $request->file('image'),
            $data['url'] ?? null,
            $data['alt'] ?? null,
        );

        return response()->json($image, 201);
    }

    public function update(Request $request, ProductImage $image): JsonResponse
    {
        $data = $request->validate([
            'alt' => ['sometimes', 'nullable', 'string', 'max:150'],
            'sort_order' => ['sometimes', 'integer', 'between:0,999'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_primary', $data)) {
            if ($data['is_primary']) {
                $this->images->makePrimary($image);
            } elseif ($image->is_primary) {
                throw new DomainException('Hãy chọn một ảnh khác làm ảnh chính.');
            }
        }

        $image->update(collect($data)->only(['alt', 'sort_order'])->all());

        return response()->json($image->fresh());
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        $this->images->delete($image);

        return response()->json(['message' => 'Đã xóa ảnh.']);
    }
}

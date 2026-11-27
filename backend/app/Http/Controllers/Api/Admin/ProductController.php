<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category:id,name')
            ->withSum(['batches as stock' => fn ($q) => $q->sellable()], 'quantity_on_hand')
            ->latest('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json($products);
    }

    public function store(Request $request, ProductImageService $images): JsonResponse
    {
        $data = $this->validated($request, creating: true);
        $file = $request->file('image');
        unset($data['image']);

        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $product = Product::create($data);

        // An uploaded photo goes into the gallery and becomes the primary image.
        if ($file !== null) {
            $images->add($product, $file, null, $product->name);
        }

        return response()->json($product->fresh()->load('images'), 201);
    }

    public function update(Request $request, Product $product, ProductImageService $images): JsonResponse
    {
        $data = $this->validated($request, creating: false);
        $file = $request->file('image');
        unset($data['image']);

        $product->update($data);

        // A new upload is added to the gallery; it does not replace the existing photos.
        if ($file !== null) {
            $images->add($product, $file, null, $product->name);
        }

        return response()->json($product->fresh()->load('images'));
    }

    /** Products that already have sales history are deactivated rather than deleted. */
    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return response()->json(['message' => 'Đã ngừng bán sản phẩm.']);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'unit' => [$required, 'string', 'max:20'],
            'price' => [$required, 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }
}

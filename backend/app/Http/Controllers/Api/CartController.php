<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->cartItems()
            ->with('product:id,name,unit,price,image_url,is_active')
            ->latest('id')
            ->get()
            ->map(fn (CartItem $item) => $this->present($item))
            ->values();

        return response()->json([
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        abort_unless($product->is_active, 422, 'Sản phẩm đã ngừng bán.');

        $user = $request->user();
        $existing = $user->cartItems()->where('product_id', $product->id)->first();
        $quantity = ($existing?->quantity ?? 0) + ($data['quantity'] ?? 1);

        $this->assertStock($product, $quantity);

        $item = $user->cartItems()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $quantity],
        );

        return response()->json($this->present($item->load('product')), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        $item = $request->user()->cartItems()->where('product_id', $product->id)->firstOrFail();
        $this->assertStock($product, $data['quantity']);

        $item->update(['quantity' => $data['quantity']]);

        return response()->json($this->present($item->load('product')));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->cartItems()->where('product_id', $product->id)->delete();

        return response()->json(['message' => 'Đã xóa khỏi giỏ hàng.']);
    }

    private function assertStock(Product $product, int $quantity): void
    {
        $available = (int) $product->batches()->sellable()->sum('quantity_on_hand');

        if ($quantity > $available) {
            throw new DomainException("Chỉ còn {$available} {$product->unit} \"{$product->name}\" trong kho.");
        }
    }

    private function present(CartItem $item): array
    {
        $product = $item->product;

        return [
            'product_id' => $item->product_id,
            'name' => $product->name,
            'unit' => $product->unit,
            'image_url' => $product->image_url,
            'price' => $product->price,
            'quantity' => $item->quantity,
            'line_total' => $product->price * $item->quantity,
            'available_stock' => (int) $product->batches()->sellable()->sum('quantity_on_hand'),
            'is_active' => $product->is_active,
        ];
    }
}

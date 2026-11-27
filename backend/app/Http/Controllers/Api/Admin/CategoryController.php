<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $data['slug'] = Str::slug($data['name']);

        return response()->json(Category::create($data), 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $category->update(['name' => $data['name'], 'slug' => Str::slug($data['name'])]);

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Đã xóa danh mục.']);
    }
}

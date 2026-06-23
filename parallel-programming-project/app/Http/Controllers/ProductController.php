<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::all());
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        $product->request_counter += 1;
        $product->save();

        return response()->json($product);
    }

    public function showRedis($id)
    {
        $cacheKey = 'product:'.$id;

        $cachedProduct = Cache::get($cacheKey);
        if ($cachedProduct !== null) {
            Cache::put($cacheKey, $cachedProduct, now()->addMinutes(10));

            return response()->json($cachedProduct);
        }

        $product = Product::findOrFail($id);
        $product->request_counter += 1;
        $product->save();
        $productArray = $product->toArray();
        Cache::put($cacheKey, $productArray, now()->addMinutes(10));

        return response()->json($productArray);
    }

    public function store(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'inventory' => 'required|integer|min:0',
            'image' => 'nullable|string',
        ]);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'inventory' => 'sometimes|integer|min:0',
            'image' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Request $request, Product $product)
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}

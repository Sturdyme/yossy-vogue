<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request) 
    {
        $query = Product::with('images');

        // Filter by category if supplied in request
        if ($request->filled('category') && !empty($request->category)) {
            $query->where('category', $request->query('category'));
        } else {
            $query->where('is_featured', true);
        }

        return $query->latest()->paginate(20);
    }

    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $products = Product::where('name', 'LIKE', "%{$q}%")
            ->orWhere('description', 'LIKE', "%{$q}%")
            ->orWhere('category', 'LIKE', "%{$q}%")
            ->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", ["{$q}%"])
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function show(Product $product)
    {
        $product->load('images');

        $data = $product->toArray();
        $data['images'] = $product->images->pluck('image_url')->values();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'brand' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0',
            'image_url' => 'nullable|string|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
        ]);

        $product = Product::create(collect($validated)->except('images')->toArray());

        if (!empty($validated['images'])) {
            foreach ($validated['images'] as $index => $url) {
                $product->images()->create([
                    'image_url' => $url,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }
        $product->load('images');
        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string|max:100',
            'brand' => 'nullable|string|max:100',
            'stock' => 'sometimes|integer|min:0',
            'image_url' => 'nullable|string|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
        ]);

        $product->update(collect($validated)->except('images')->toArray());

        if (isset($validated['images'])) {
            $product->images()->delete();
            foreach ($validated['images'] as $index => $url) {
                $product->images()->create([
                    'image_url' => $url,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }
        $product->load('images');
        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
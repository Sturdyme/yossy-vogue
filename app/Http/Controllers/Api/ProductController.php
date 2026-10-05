<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request) 
    {
        $query = Product::with('images');

       if ($request->filled('category')) {
        $query->where('category', $request->query('category'));
       } elseif (!$request->boolean('all')) {
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

    $likeOperator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

    $products = Product::where('name', $likeOperator, "%{$q}%")
        ->orWhere('description', $likeOperator, "%{$q}%")
        ->orWhere('category', $likeOperator, "%{$q}%")
        ->orderByRaw("CASE WHEN name {$likeOperator} ? THEN 0 ELSE 1 END", ["{$q}%"])
        ->orderBy('name')
        ->limit(10)
        ->get();

    return response()->json($products);
}

public function deals()
{
    return Product::where('is_deal', true)
    ->latest()
    ->take(24)
    ->get();
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
            'category' => ['required_unless:is_deal,1', 'nullable', 'string'],
            'brand' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0',
            'image' => 'required|image|max:4096',
            'is_featured' => 'nullable|boolean',
            'is_deal' => 'nullable|boolean',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:4096',
        ]);
       
        Configuration::instance(env('CLOUDINARY_URL'));
        $cloudinary = new Cloudinary();

        $uploadImage = $cloudinary->uploadApi()->upload(
            $request->file('image')->getRealPath(),
            ['folder' => 'products']
        );
        $imageUrl = $uploadImage['secure_url'];

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'category' => $validated['category'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'stock' => $validated['stock'],
            'is_featured' => $request->boolean('is_featured'),
            'is_deal' => $request->boolean('is_deal'),
            'image_url' => $imageUrl
        ]);

        $product->images()->create([
            'image_url' => $imageUrl,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $index => $file) {
                $uploaded = $cloudinary->uploadApi()->upload(
                    $file->getRealPath(),
                    ['folder' => 'products']
                );
                $product->images()->create([
                    'image_url' => $uploaded['secure_url'],
                    'sort_order' => $index + 1,
                    'is_primary' => false,
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
            'is_featured' => 'nullable|boolean',
            'is_deal' => 'nullable|boolean',
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
        $product->load('images');

        foreach ($product->images as $image) {
            $path = str_replace('/storage/', '', parse_url($image->image_url, PHP_URL_PATH));
            \Storage::disk('public')->delete($path);
        }
        $product->delete();
        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
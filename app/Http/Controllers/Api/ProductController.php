<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

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
        $raw = $request->query('q', '');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, 100) : '';

        if ($q === '') {
            return response()->json([]);
        }

        // Escape LIKE wildcards so "%" or "_" typed by a visitor is searched literally.
        $escaped = addcslashes($q, '%_\\');
        $like = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        $products = Product::where('name', $like, "%{$escaped}%")
            ->orWhere('description', $like, "%{$escaped}%")
            ->orWhere('category', $like, "%{$escaped}%")
            ->orderByRaw("CASE WHEN name {$like} ? THEN 0 ELSE 1 END", ["{$escaped}%"])
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

    // The route /products/featured pointed at this method, but it did not exist (a 500 when called).
    public function featured()
    {
        return Product::with('images')
            ->where('is_featured', true)
            ->latest()
            ->take(12)
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
            'description' => 'required|string|max:5000',
            'price' => 'required|numeric|min:0|max:100000000',
            'category' => ['required_unless:is_deal,1', 'nullable', 'string', 'max:100'],
            'brand' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0|max:1000000',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'is_featured' => 'nullable|boolean',
            'is_deal' => 'nullable|boolean',
            'gallery' => 'nullable|array|max:8',
            'gallery.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $uploaded = []; // every URL sent to Cloudinary so far, for cleanup if something fails

        try {
            $cloudinary = $this->cloudinary();

            $mainUrl = $this->upload($cloudinary, $request->file('image'));
            $uploaded[] = $mainUrl;

            $galleryUrls = [];
            foreach ($request->file('gallery', []) as $file) {
                $url = $this->upload($cloudinary, $file);
                $uploaded[] = $url;
                $galleryUrls[] = $url;
            }

            $product = DB::transaction(function () use ($validated, $request, $mainUrl, $galleryUrls) {
                $product = Product::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'],
                    'price' => $validated['price'],
                    'category' => $validated['category'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'stock' => $validated['stock'],
                    'is_featured' => $request->boolean('is_featured'),
                    'is_deal' => $request->boolean('is_deal'),
                    'image_url' => $mainUrl,
                ]);

                $product->images()->create([
                    'image_url' => $mainUrl,
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);

                foreach ($galleryUrls as $index => $url) {
                    $product->images()->create([
                        'image_url' => $url,
                        'sort_order' => $index + 1,
                        'is_primary' => false,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $e) {
            // Do not leave orphaned files behind in Cloudinary.
            $this->deleteFromCloudinary($uploaded);
            logger()->error('Product creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Could not save the product. Please try again.',
            ], 500);
        }

        return response()->json($product->load('images'), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:5000',
            'price' => 'sometimes|numeric|min:0|max:100000000',
            'category' => 'sometimes|nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'stock' => 'sometimes|integer|min:0|max:1000000',
            'image_url' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'images' => 'nullable|array|max:12',
            'images.*' => ['url', 'starts_with:https://', 'max:2048'],
            'is_featured' => 'nullable|boolean',
            'is_deal' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($product, $validated) {
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
        });

        $product->load('images');

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->load('images');

        // Only images that no OTHER product uses are removed from Cloudinary.
        $imageModel = $product->images()->getRelated();
        $foreignKey = $product->images()->getForeignKeyName();

        $deletable = $product->images->pluck('image_url')
            ->push($product->image_url)
            ->filter()
            ->unique()
            ->reject(function ($url) use ($product, $imageModel, $foreignKey) {
                return Product::where('id', '!=', $product->id)->where('image_url', $url)->exists()
                    || $imageModel->newQuery()->where($foreignKey, '!=', $product->id)->where('image_url', $url)->exists();
            })
            ->values()
            ->all();

        // If Product uses SoftDeletes the row can be restored, so its images are kept.
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($product), true);

        try {
            $product->delete();
        } catch (QueryException $e) {
            // 23000 = MySQL integrity violation, 23503 = PostgreSQL foreign key violation
            if (in_array((string) $e->getCode(), ['23000', '23503'], true)) {
                return response()->json([
                    'message' => 'This product is linked to existing orders and cannot be deleted. Set its stock to 0 so it can no longer be bought.',
                ], 409);
            }

            throw $e;
        }

        if (!$softDeletes) {
            $this->deleteFromCloudinary($deletable);
        }

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Cloudinary helpers                                                  */
    /* ------------------------------------------------------------------ */

    private function cloudinary(): Cloudinary
    {
        // Read from config (config/services.php), not env(): env() is unreliable once config is cached.
        Configuration::instance(config('services.cloudinary.url'));

        return new Cloudinary();
    }

    private function upload(Cloudinary $cloudinary, UploadedFile $file): string
    {
        $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'products',
            'resource_type' => 'image',
        ]);

        return $result['secure_url'];
    }

    /**
     * https://res.cloudinary.com/<cloud>/image/upload/v123/products/abc.jpg  ->  products/abc
     */
    private function publicIdFromUrl(string $url): ?string
    {
        if (!str_contains($url, 'res.cloudinary.com')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return preg_match('#/upload/(?:v\d+/)?(.+)\.[A-Za-z0-9]+$#', $path, $m) ? $m[1] : null;
    }

    private function deleteFromCloudinary(array $urls): void
    {
        $publicIds = collect($urls)
            ->filter()
            ->map(fn ($url) => $this->publicIdFromUrl($url))
            ->filter()
            ->unique();

        if ($publicIds->isEmpty()) {
            return;
        }

        try {
            $api = $this->cloudinary()->uploadApi();

            foreach ($publicIds as $publicId) {
                $api->destroy($publicId);
            }
        } catch (\Throwable $e) {
            logger()->warning('Cloudinary cleanup failed: ' . $e->getMessage());
        }
    }
}

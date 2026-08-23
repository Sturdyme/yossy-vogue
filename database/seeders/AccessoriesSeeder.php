<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccessoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        $accessoriesItems = [
            [
                'name' => 'Classic White Sneakers',
                'description' => 'Minimalist leather sneakers, perfect for everyday wear.',
                'price' => 59.99,
                'category' => 'Accessories',
                'brand' => 'Oraimo',
                'stock' => 25,
                'image_url' => 'https://picsum.photos/seed/sneakers/400',
                'images' => [
                    'https://picsum.photos/seed/sneakers1/400',
                    'https://picsum.photos/seed/sneakers2/400',
                ],
            ],
            // more clothing items...
        ];

        foreach ($accessoriesItems as $productData) {
            $images = $productData['images'] ?? [];
            unset($productData['images']);

            $product = Product::create($productData);

            foreach ($images as $index => $url) {
                $product->images()->create([
                    'image_url'  => $url,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }
    }
}

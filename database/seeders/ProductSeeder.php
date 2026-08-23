<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Classic White Sneakers',
                'description' => 'Minimalist leather sneakers, perfect for everyday wear.',
                'price' => 59.99,
                'category' => '',
                'brand' => 'Genesis Footwear',
                'stock' => 25,
                'image_url' => 'https://picsum.photos/seed/sneakers/400',
                'is_featured' => true,
                'images' => [
                    'https://picsum.photos/seed/sneakers/400',
                    'https://picsum.photos/seed/sneakers1/400',
                    'https://picsum.photos/seed/sneakers2/400',
                    'https://i.ibb.co/Hch91tS/IMG-1348.jpg',
                ],
            ],
            [
                'name' => 'Oversized Denim Jacket',
                'description' => 'A relaxed-fit denim jacket with vintage wash.',
                'price' => 84.50,
                'category' => '',
                'brand' => 'Sturdy Threads',
                'stock' => 15,
                'image_url' => 'https://picsum.photos/seed/denim/400',
                'is_featured' => true,
                'images' => [
                    'https://picsum.photos/seed/denim/400',
                    'https://picsum.photos/seed/denim1/400',
                    'https://picsum.photos/seed/denim2/400',
                    'https://picsum.photos/seed/denim3/400',
                ],
            ],
            [
                'name' => 'Silk Wrap Blouse',
                'description' => 'Elegant silk blouse with a flattering wrap silhouette.',
                'price' => 45.00,
                'category' => '',
                'brand' => 'Yuna Collectives',
                'stock' => 30,
                'image_url' => 'https://picsum.photos/seed/blouse/400',
                'is_featured' => true,
                'images' => [
                    'https://picsum.photos/seed/blouse/400',
                    'https://picsum.photos/seed/blouse1/400',
                    'https://picsum.photos/seed/blouse2/400',
                ],
            ],
            [
                'name' => 'Pleated Midi Skirt',
                'description' => 'Flowy pleated skirt, falls just below the knee.',
                'price' => 38.00,
                'category' => '',
                'brand' => 'VersaStyle',
                'stock' => 20,
                'image_url' => 'https://picsum.photos/seed/skirt/400',
                'is_featured' => true,
                'images' => [
                    'https://picsum.photos/seed/skirt/400',
                    'https://picsum.photos/seed/skirt1/400',
                    'https://picsum.photos/seed/skirt2/400',
                ],
            ],
            [
                'name' => 'Leather Crossbody Bag',
                'description' => 'Compact crossbody bag in genuine leather.',
                'price' => 72.00,
                'category' => '',
                'brand' => 'Nvidia',
                'stock' => 10,
                'image_url' => 'https://picsum.photos/seed/bag/400',
                'is_featured' => true,
                'images' => [
                    'https://picsum.photos/seed/bag/400',
                    'https://picsum.photos/seed/bag1/400',
                    'https://picsum.photos/seed/bag2/400',
                    'https://picsum.photos/seed/bag3/400',
                ],
            ],
        ];

        foreach ($products as $productData) {
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
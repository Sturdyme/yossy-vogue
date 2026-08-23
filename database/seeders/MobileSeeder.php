<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MobileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        $products = [
            [
                'name' => 'Iphone 11 Pro max',
                'description' => 'Minimalist leather sneakers, perfect for everyday wear.',
                'price' => 59.99,
                'category' => 'Mobiles',
                'brand' => 'Iphone',
                'stock' => 25,
                'image_url' => 'https://picsum.photos/seed/sneakers/400',
                'images' => [
                    'https://picsum.photos/seed/sneakers1/400',
                    'https://picsum.photos/seed/sneakers2/400',
                ],
            ],
                       [
                'name' => 'Redmi Note 13',
                'description' => 'Budget-friendly Android phone with a 108MP camera.',
                'price' => 189.00,
                'category' => 'Mobiles',
                'brand' => 'Xiaomi',
                'stock' => 30,
                'image_url' => 'https://picsum.photos/seed/phone2/400',
                'images' => [
                    'https://picsum.photos/seed/phone2a/400',
                    'https://picsum.photos/seed/phone2b/400',
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

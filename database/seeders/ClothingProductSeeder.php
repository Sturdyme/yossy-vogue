<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ClothingProductSeeder extends Seeder
{
    public function run(): void
    {
        $clothingItems = [
            [
                'name' => 'Leather Crossbody Bag',
                'description' => 'Compact crossbody bag in genuine leather.',
                'price' => 72.00,
                'category' => 'Clothing',
                'brand' => 'Nvidia',
                'stock' => 10,
                'image_url' => 'https://picsum.photos/seed/bag/400',
                'images' => [
                    'https://picsum.photos/seed/bag/400',
                    'https://picsum.photos/seed/bag1/400',
                    'https://picsum.photos/seed/bag2/400',
                    'https://picsum.photos/seed/bag3/400',
                ],
            ],
        ];

        foreach ($clothingItems as $item) {
            $images = $item['images'] ?? [];
            unset($item['images']);

            $product = Product::create($item);

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
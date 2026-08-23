<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CamerasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cameraItems = [
            [
                'name' => 'Leather Crossbody Bag',
                'description' => 'Compact crossbody bag in genuine leather.',
                'price' => 72.00,
                'category' => 'Cameras',
                'brand' => 'Rode Express',
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

        foreach ($cameraItems as $item) {
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

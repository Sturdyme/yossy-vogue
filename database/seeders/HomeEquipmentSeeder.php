<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class HomeEquipmentSeeder extends Seeder
{
      public function run(): void
    {
        $homeEquipmentItems = [
            [
                'name' => '12 by 12 Nature Wall Frame',
                'description' => 'Compact crossbody bag in genuine leather.',
                'price' => 72.00,
                'category' => 'Home Appliances',
                'brand' => 'Lush Huar',
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

        foreach ($homeEquipmentItems as $item) {
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
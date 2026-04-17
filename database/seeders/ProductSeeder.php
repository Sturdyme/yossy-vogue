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
        Product::create([
            'name' => 'Luxury Hair Extension',
            'price' => 15000,
            'description' => 'High-quality Brazilian silk hair',
            'category' => 'Hair Extensions & wigs',
        ]);
    }
}

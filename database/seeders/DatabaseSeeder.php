<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            ProductSeeder::class,
            ClothingProductSeeder::class,
            HomeEquipmentSeeder::class,
            ElectronicSeeder::class,
            MobileSeeder::class,
            AccessoriesSeeder::class,
            HairWigs::class,
            HomeTheaters::class,
            CamerasSeeder::class,
            ChairsSeeder::class,
            ToysSeeder::class,
        ]);
    }
}

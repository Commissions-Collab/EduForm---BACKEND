<?php

namespace Database\Seeders;

use App\Models\BookInventory;
use Illuminate\Database\Seeder;

class BookInventorySeeder extends Seeder
{
    public function run(): void
    {
        // Create 50 different books using the BookInventoryFactory
        BookInventory::factory(50)->create();
    }
}
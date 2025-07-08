<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'email' => 'superAdmin@gmail.com',
            'password' => bcrypt('secret'),
        ]);

        User::factory()->teacher()->create([
            'email' => 'teacher1@gmail.com',
            'password' => bcrypt('secret'),
        ]);

        User::factory()->teacher()->create([
            'email' => 'teacher2@gmail.com',
            'password' => bcrypt('secret'),
        ]);

        User::factory()->count(10)->create([
            'password' => bcrypt('secret'),
        ]);
    }
}

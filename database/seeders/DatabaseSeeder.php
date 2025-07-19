<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Use this artisan command 'php artisan migrate:fresh --seed' or 'php artisan db:seed' to run this seeder.
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

        User::factory()->count(10)->create();

        Student::factory()->count(10)->create();
    }
}

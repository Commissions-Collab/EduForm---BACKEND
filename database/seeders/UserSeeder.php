<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin
        User::create([
            'email' => 'admin@school.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        User::factory()->teacher()->count(1)->create([
            'email' => 'registrar@gmail.com',
            'password' => Hash::make('password'),
        ]);

        // Create additional users that will be used by teachers and students
        User::factory()->teacher()->count(19)->create();
        User::factory()->student()->count(200)->create();
    }
}

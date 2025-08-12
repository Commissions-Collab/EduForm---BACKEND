<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // Get all teacher users
        $teacherUsers = User::where('role', 'teacher')->get();

        foreach ($teacherUsers as $user) {
            Teacher::create([
                'user_id' => $user->id,
                'employee_id' => 'EMP-' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
                'first_name' => fake()->firstName(),
                'middle_name' => fake()->optional(0.7)->firstName(),
                'last_name' => fake()->lastName(),
                'phone' => fake()->optional(0.8)->phoneNumber(),
                'hire_date' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
                'employment_status' => 'active',
            ]);
        }
    }
}
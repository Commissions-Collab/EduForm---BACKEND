<?php

namespace Database\Seeders;

use App\Models\Teacher;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // Create realistic number of teachers for high school (25-35)
        Teacher::factory(30)->create();
    }
}
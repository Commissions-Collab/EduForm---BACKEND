<?php

namespace Database\Seeders;

use App\Models\YearLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class YearLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $yearLevels = [
            ['name' => 'Grade 7', 'code' => 'G7', 'sort_order' => 1],
            ['name' => 'Grade 8', 'code' => 'G8', 'sort_order' => 2],
            ['name' => 'Grade 9', 'code' => 'G9', 'sort_order' => 3],
            ['name' => 'Grade 10', 'code' => 'G10', 'sort_order' => 4],
            ['name' => 'Grade 11', 'code' => 'G11', 'sort_order' => 5],
            ['name' => 'Grade 12', 'code' => 'G12', 'sort_order' => 6],
        ];

        foreach ($yearLevels as $yearLevel) {
            YearLevel::create($yearLevel);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\YearLevel;
use Illuminate\Database\Seeder;

class YearLevelSeeder extends Seeder
{
    public function run(): void
    {
        $yearLevels = [
            ['name' => 'Grade 7', 'code' => 'G7', 'sort_order' => 7],
            ['name' => 'Grade 8', 'code' => 'G8', 'sort_order' => 8],
            ['name' => 'Grade 9', 'code' => 'G9', 'sort_order' => 9],
            ['name' => 'Grade 10', 'code' => 'G10', 'sort_order' => 10],
            ['name' => 'Grade 11', 'code' => 'G11', 'sort_order' => 11],
            ['name' => 'Grade 12', 'code' => 'G12', 'sort_order' => 12],
        ];

        foreach ($yearLevels as $yearLevel) {
            YearLevel::create($yearLevel);
        }
    }
}
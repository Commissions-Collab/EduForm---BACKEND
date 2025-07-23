<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScheduleExceptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = Schedule::all();
        
        // Create some schedule exceptions (cancellations, makeup classes, etc.)
        foreach ($schedules->random(10) as $schedule) {
            // Random date in the current month
            $exceptionDate = Carbon::now()->addDays(rand(-30, 30))->format('Y-m-d');
            
            $types = ['cancelled', 'moved', 'makeup'];
            $type = $types[array_rand($types)];
            
            $data = [
                'schedule_id' => $schedule->id,
                'date' => $exceptionDate,
                'type' => $type,
                'reason' => $this->getReasonForType($type),
            ];
            
            if ($type === 'moved' || $type === 'makeup') {
                $originalStart = Carbon::parse($schedule->start_time);
                $newStart = $originalStart->copy()->addHours(rand(1, 3));
                $newEnd = $newStart->copy()->addHour();
                
                $data['new_start_time'] = $newStart->format('H:i:s');
                $data['new_end_time'] = $newEnd->format('H:i:s');
                $data['new_room'] = 'Room ' . rand(201, 210);
            }
            
            ScheduleException::create($data);
        }
    }
    
    private function getReasonForType($type)
    {
        $reasons = [
            'cancelled' => [
                'Teacher is sick',
                'School assembly',
                'Emergency drill',
                'Weather condition',
                'Power outage'
            ],
            'moved' => [
                'Room maintenance',
                'Schedule conflict',
                'Equipment setup needed',
                'Guest speaker session'
            ],
            'makeup' => [
                'Makeup class for previous cancellation',
                'Additional review session',
                'Catch up on missed topics',
                'Exam preparation'
            ]
        ];
        
        return $reasons[$type][array_rand($reasons[$type])];
    }
}

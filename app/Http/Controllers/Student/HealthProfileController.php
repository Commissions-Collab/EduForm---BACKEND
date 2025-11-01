<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\StudentBmi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HealthProfileController extends Controller
{
    public function getHealthProfileData()
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $currentYear = $this->getCurrentAcademicYear();

            // Fetch BMI records for the student in current academic year
            $BMIdata = StudentBmi::where('student_id', $student->id)
                ->where('academic_year_id', $currentYear->id)
                ->with(['quarter'])
                ->orderBy('recorded_at', 'desc')
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'recorded_at' => $record->recorded_at,
                        'height_cm' => $record->height_cm,
                        'weight_kg' => $record->weight_kg,
                        'bmi' => $record->bmi,
                        'bmi_category' => $record->bmi_category,
                        'quarter_id' => $record->quarter_id,
                        'quarter_name' => $record->quarter?->name ?? 'Unknown',
                        'remarks' => $record->remarks,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $BMIdata
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch health profile data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getCurrentAcademicYear()
    {
        try {
            // Try with boolean first
            $academicYear = AcademicYear::where('is_current', true)->first();

            // If not found, try with integer 1
            if (!$academicYear) {
                $academicYear = AcademicYear::where('is_current', 1)->first();
            }

            // If still not found, get the most recent one
            if (!$academicYear) {
                $academicYear = AcademicYear::orderBy('id', 'desc')->first();
            }

            if (!$academicYear) {
                throw new \Exception('No academic year found in database');
            }

            return $academicYear;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}

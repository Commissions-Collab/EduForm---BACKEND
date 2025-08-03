<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\StudentBmi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HealthProfileController extends Controller
{
    public function getHealthProfileData() {
        $student = Auth::user();

        $currentYear = $this->getCurrentAcademicYear();

        $BMIdata = StudentBmi::where('student_id', $student->id)
            ->where('academic_year_id', $currentYear->id)
            ->get();

        return response()->json([
            'data' => $BMIdata
        ]);
    }

    private function getCurrentAcademicYear()
    {
        $year = AcademicYear::where('is_current', 1)->first();
        if (!$year) {
            abort(404, 'Active academic year not found.');
        }
        return $year;
    }
}

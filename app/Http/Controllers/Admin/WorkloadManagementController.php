<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkloadManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        // Get current academic year and quarter
        $currentAcademicYear = $this->getCurrentAcademicYear();
        $currentQuarter = $this->getCurrentQuarter($currentAcademicYear->id);

        $selectedAcademicYearId = $request->get('academic_year_id', $currentAcademicYear->id);
        $selectedQuarterId = $request->get('quarter_id', $currentQuarter->id);

        // Get workload data
        $workloadData = $this->getWorkloadData($teacher->id, $selectedAcademicYearId, $selectedQuarterId);
        $availableQuarters = $this->getAvailableQuarters($selectedAcademicYearId);

        return response()->json([
            'success' => true,
            'data' => $workloadData,
            'current_academic_year' => $currentAcademicYear,
            'current_quarter' => $currentQuarter,
            'available_quarters' => $availableQuarters
        ]);
    }

    private function getWorkloadData($teacherId, $academicYearId, $quarterId)
    {
        $teacherSubjects = TeacherSubject::with([
            'subject:id,name,code',
            'section.students',
            'quarter:id,name',
        ])
            ->where('teacher_id', $teacherId)
            ->where('academic_year_id', $academicYearId)
            ->where('quarter_id', $quarterId)
            ->get();

        $totalStudents = $teacherSubjects->sum(function ($ts) {
            return $ts->section?->students->count() ?? 0;
        });

        $subjectAreas = $teacherSubjects->pluck('subject.name')->filter()->unique();
        $subjectCount = $subjectAreas->count();

        $classSections = $teacherSubjects->pluck('section_id')->unique();
        $classSectionCount = $classSections->count();

        $advisoryDuties = Section::whereHas('advisors', function ($query) use ($teacherId, $academicYearId) {
            $query->where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId);
        })
            ->count();

        $teachingLoadDetails = $teacherSubjects->groupBy('section.name')->map(function ($subjects, $sectionName) use ($teacherId) {
            $section = $subjects->first()->section;
            $quarter = $subjects->first()->quarter;

            $studentCount = $section?->students->count() ?? 0;
            $subjectNames = $subjects->pluck('subject.name')->filter()->toArray();
            $hasAdvisoryRole = optional($section->advisor)->teacher_id == $teacherId;

            return [
                'section' => $sectionName,
                'grade_level' => $section->yearLevel->name ?? null,
                'students' => $studentCount,
                'subjects' => $subjectNames,
                'subjects_display' => implode(', ', $subjectNames),
                'advisory_role' => $hasAdvisoryRole ? 'Yes' : 'No',
                'quarter' => $quarter->name ?? null,
                'is_current' => true
            ];
        })->values();

        $quarterComparison = $this->getQuarterComparison($teacherId, $academicYearId);

        return [
            'summary' => [
                'total_students' => $totalStudents,
                'subject_areas' => $subjectCount,
                'class_sections' => $classSectionCount,
                'advisory_duties' => $advisoryDuties,
            ],
            'teaching_load_details' => $teachingLoadDetails,
            'subject_areas_list' => $subjectAreas->toArray(),
            'quarter_comparison' => $quarterComparison
        ];
    }

    private function getQuarterComparison($teacherId, $academicYearId)
    {
        $quarters = Quarter::where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get();

        $comparison = [];

        foreach ($quarters as $quarter) {
            $teacherSubjects = TeacherSubject::with(['section.students'])
                ->where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->where('quarter_id', $quarter->id)
                ->get();

            $totalStudents = $teacherSubjects->sum(function ($ts) {
                return $ts->section?->students->count() ?? 0;
            });

            $subjectCount = $teacherSubjects->pluck('subject_id')->unique()->count();
            $sectionCount = $teacherSubjects->pluck('section_id')->unique()->count();

            // Get relevant schedules
            $schedules = Schedule::where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->whereIn('section_id', $teacherSubjects->pluck('section_id')->unique())
                ->whereIn('subject_id', $teacherSubjects->pluck('subject_id')->unique())
                ->get();

            // Calculate total hours per week
            $hoursPerWeek = $schedules->sum(function ($schedule) {
                $start = Carbon::createFromTimeString($schedule->start_time);
                $end = Carbon::createFromTimeString($schedule->end_time);
                return $end->floatDiffInHours($start); // accurate decimal
            });

            $comparison[] = [
                'quarter_id' => $quarter->id,
                'quarter_name' => $quarter->name,
                'total_students' => $totalStudents,
                'subject_areas' => $subjectCount,
                'class_sections' => $sectionCount,
                'hours_per_week' => round($hoursPerWeek, 2),
                'is_current' => $quarter->is_current ?? false
            ];
        }

        return $comparison;
    }


    private function getCurrentAcademicYear()
    {
        return AcademicYear::where('is_current', true)->first()
            ?? AcademicYear::latest()->first();
    }

    private function getCurrentQuarter($academicYearId)
    {
        return Quarter::where('academic_year_id', $academicYearId)
            ->orderBy('start_date')
            ->first();
    }

    private function getAvailableQuarters($academicYearId)
    {
        return Quarter::where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get(['id', 'name']);
    }
}

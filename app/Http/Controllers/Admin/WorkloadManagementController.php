<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\TeacherSubject;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WorkloadManagementController extends Controller
{
    public function index(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workload',
                'error' => $e->getMessage()
            ], 500);
        }
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

        $teachingLoadDetails = $teacherSubjects->groupBy('section.name')->map(function ($subjects, $sectionName) use ($teacherId, $academicYearId, $quarterId) {
            $section = $subjects->first()->section;
            $quarter = $subjects->first()->quarter;

            $studentCount = $section?->students->count() ?? 0;
            $subjectNames = $subjects->pluck('subject.name')->filter()->toArray();
            $hasAdvisoryRole = $section->currentAdvisor()?->id === $teacherId;

            // Calculate hours per week for this section's subjects
            $subjectIds = $subjects->pluck('subject_id')->unique()->filter()->values();
            $sectionId = $section->id;

            $hoursPerWeek = 0;
            $subjectHours = [];

            if (!$subjectIds->isEmpty() && $sectionId) {
                // Get schedules for this section and subjects
                $schedules = Schedule::where('teacher_id', $teacherId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('quarter_id', $quarterId)
                    ->where('section_id', $sectionId)
                    ->whereIn('subject_id', $subjectIds->toArray())
                    ->with('subject:id,name')
                    ->get();

                // Calculate total hours and hours per subject
                $schedulesBySubject = $schedules->groupBy('subject_id');

                foreach ($schedulesBySubject as $subjectId => $subjectSchedules) {
                    $subjectName = $subjectSchedules->first()->subject->name ?? 'Unknown';
                    $subjectHoursTotal = 0;

                    foreach ($subjectSchedules as $schedule) {
                        try {
                            $start = Carbon::createFromTimeString($schedule->start_time);
                            $end = Carbon::createFromTimeString($schedule->end_time);
                            $subjectHoursTotal += $end->floatDiffInHours($start);
                        } catch (Exception $e) {
                            Log::error('Error calculating schedule hours: ' . $e->getMessage());
                        }
                    }

                    $subjectHours[$subjectName] = round(abs($subjectHoursTotal), 2);
                    $hoursPerWeek += $subjectHoursTotal;
                }
            }

            return [
                'section' => $sectionName,
                'grade_level' => $section->yearLevel->name ?? null,
                'students' => $studentCount,
                'subjects' => $subjectNames,
                'subjects_display' => implode(', ', $subjectNames),
                'advisory_role' => $hasAdvisoryRole ? 'Yes' : 'No',
                'quarter' => $quarter->name ?? null,
                'hours_per_week' => round(abs($hoursPerWeek), 2),
                'subject_hours' => $subjectHours, // Hours breakdown per subject
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

            $sectionIds = $teacherSubjects->pluck('section_id')->unique()->filter()->values();
            $subjectIds = $teacherSubjects->pluck('subject_id')->unique()->filter()->values();

            if ($sectionIds->isEmpty() || $subjectIds->isEmpty()) {
                $hoursPerWeek = 0;
            } else {
                $schedules = Schedule::where('teacher_id', $teacherId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('quarter_id', $quarter->id)
                    ->whereIn('section_id', $sectionIds->toArray())
                    ->whereIn('subject_id', $subjectIds->toArray())
                    ->get();

                $hoursPerWeek = $schedules->sum(function ($schedule) {
                    try {
                        $start = Carbon::createFromTimeString($schedule->start_time);
                        $end = Carbon::createFromTimeString($schedule->end_time);
                        return $end->floatDiffInHours($start);
                    } catch (Exception $e) {
                        return 0;
                    }
                });
            }

            $today = Carbon::today();
            $isCurrent = $today->between(
                Carbon::parse($quarter->start_date),
                Carbon::parse($quarter->end_date)
            );

            $comparison[] = [
                'quarter_id' => $quarter->id,
                'quarter_name' => $quarter->name,
                'total_students' => $totalStudents,
                'subject_areas' => $subjectCount,
                'class_sections' => $sectionCount,
                'hours_per_week' => round(abs($hoursPerWeek), 2),
                'is_current' => $isCurrent,
                'start_date' => $quarter->start_date,
                'end_date' => $quarter->end_date
            ];
        }

        return $comparison;
    }

    private function getCurrentAcademicYear()
    {
        try {
            // Try with boolean true first
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

    private function getCurrentQuarter($academicYearId)
    {
        $today = Carbon::today();

        // First try to find a quarter where today falls between start_date and end_date
        $currentQuarter = Quarter::where('academic_year_id', $academicYearId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        // Final fallback to first quarter by start date
        if (!$currentQuarter) {
            $currentQuarter = Quarter::where('academic_year_id', $academicYearId)
                ->orderBy('start_date')
                ->first();
        }

        return $currentQuarter;
    }

    private function getAvailableQuarters($academicYearId)
    {
        return Quarter::where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get(['id', 'name']);
    }
}

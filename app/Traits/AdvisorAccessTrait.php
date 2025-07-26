<?php

namespace App\Traits;

use App\Models\SectionAdvisor;
use Illuminate\Http\JsonResponse;

trait AdvisorAccessTrait
{
    /**
     * Verify that the authenticated teacher is the section advisor
     */
    protected function verifySectionAdvisorAccess($sectionId, $teacherId, $academicYearId)
    {
        return SectionAdvisor::with(['section.yearLevel'])
            ->where('section_id', $sectionId)
            ->where('teacher_id', $teacherId)
            ->where('academic_year_id', $academicYearId)
            ->first();
    }

    /**
     * Check if teacher is advisor and return appropriate response
     */
    protected function requireAdvisorAccess($sectionId, $teacherId, $academicYearId): ?JsonResponse
    {
        $sectionAdvisor = $this->verifySectionAdvisorAccess($sectionId, $teacherId, $academicYearId);
        
        if (!$sectionAdvisor) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only section advisors can access this resource.',
                'error_code' => 'ADVISOR_ACCESS_REQUIRED'
            ], 403);
        }

        return null; // No error, access granted
    }

    /**
     * Get section advisor with section details
     */
    protected function getSectionAdvisorWithDetails($sectionId, $teacherId, $academicYearId)
    {
        return SectionAdvisor::with([
            'section.yearLevel',
            'section.students' => function($query) {
                $query->where('enrollment_status', 'enrolled')
                      ->orderBy('last_name')
                      ->orderBy('first_name');
            },
            'teacher.user',
            'academicYear'
        ])
        ->where('section_id', $sectionId)
        ->where('teacher_id', $teacherId)
        ->where('academic_year_id', $academicYearId)
        ->first();
    }
}
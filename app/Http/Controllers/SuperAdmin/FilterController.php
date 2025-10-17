<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Section;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    /**
     * Get filter options for Super Admin
     * Super Admin can view all sections across all academic years
     */
    public function getFilterOptions()
    {
        try {
            // Get all academic years with quarters
            $academicYears = AcademicYear::with(['quarters'])
                ->select('id', 'name', 'is_current')
                ->orderBy('is_current', 'desc')
                ->orderBy('name', 'desc')
                ->get()
                ->map(function ($year) {
                    return [
                        'id' => (int) $year->id,
                        'name' => (string) $year->name,
                        'is_current' => (bool) $year->is_current,
                        'quarters' => $year->quarters->map(function ($quarter) {
                            return [
                                'id' => (int) $quarter->id,
                                'name' => (string) $quarter->name,
                                'quarter_number' => (int) $quarter->quarter_number,
                            ];
                        })->values()->toArray(),
                    ];
                })
                ->values()
                ->toArray();

            // Get all sections with year level
            $sections = Section::with(['yearLevel'])
                ->select('sections.id', 'sections.name', 'sections.year_level_id')
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($section) {
                    return [
                        'id' => (int) $section->id,
                        'name' => (string) $section->name,
                        'year_level' => $section->yearLevel ? (string) $section->yearLevel->name : 'N/A',
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'academic_years' => $academicYears,
                'quarters' => $this->getDefaultQuarters(),
                'sections' => $sections,
            ]);
        } catch (\Exception $e) {
            \Log::error('FilterController@getFilterOptions - Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch filter options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sections for a specific academic year
     * Super Admin can view all sections
     */
    public function getSectionsForAcademicYear($academicYearId)
    {
        try {
            // Validate academic year exists
            $academicYear = AcademicYear::findOrFail($academicYearId);

            // Get all sections (no restriction for super admin)
            $sections = Section::with(['yearLevel'])
                ->select('sections.id', 'sections.name', 'sections.year_level_id')
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($section) {
                    return [
                        'id' => (int) $section->id,
                        'name' => (string) $section->name,
                        'year_level' => $section->yearLevel ? (string) $section->yearLevel->name : 'N/A',
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'academic_year_id' => (int) $academicYearId,
                'academic_year' => [
                    'id' => (int) $academicYear->id,
                    'name' => (string) $academicYear->name,
                    'is_current' => (bool) $academicYear->is_current,
                ],
                'sections' => $sections,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::warning('FilterController@getSectionsForAcademicYear - Academic year not found: ' . $academicYearId);

            return response()->json([
                'success' => false,
                'message' => 'Academic year not found',
                'error' => 'The requested academic year does not exist.',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('FilterController@getSectionsForAcademicYear - Error: ' . $e->getMessage(), [
                'academicYearId' => $academicYearId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sections',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get default quarters (1st to 4th quarter)
     */
    private function getDefaultQuarters()
    {
        return [
            ['id' => 1, 'name' => '1st Quarter', 'quarter_number' => 1],
            ['id' => 2, 'name' => '2nd Quarter', 'quarter_number' => 2],
            ['id' => 3, 'name' => '3rd Quarter', 'quarter_number' => 3],
            ['id' => 4, 'name' => '4th Quarter', 'quarter_number' => 4],
        ];
    }
}

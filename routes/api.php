<?php

use App\Http\Controllers\Admin\AcademicRecordsController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendancePDFController;
use App\Http\Controllers\Admin\BookManagementController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ParentsConferenceController;
use App\Http\Controllers\Admin\PromotionReportController;
use App\Http\Controllers\Admin\StudentApprovalController;
use App\Http\Controllers\Admin\StudentBmiController;
use App\Http\Controllers\Admin\WorkloadManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SuperAdmin\ScheduleController;
use App\Http\Controllers\SuperAdmin\SectionController;
use App\Http\Controllers\SuperAdmin\TeacherController;
use App\Http\Controllers\Admin\MonthlyAttendanceController;

use App\Http\Controllers\Student\AchievementsController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\GradeController;
use App\Http\Controllers\Student\HealthProfileController;
use App\Http\Controllers\Student\StudentAttendanceController;
use App\Http\Controllers\SuperAdmin\AcademicCalendarController;
use App\Http\Controllers\SuperAdmin\AcademicYearController;
use App\Http\Controllers\SuperAdmin\EnrollmentController;
use App\Http\Controllers\SuperAdmin\StudentController;
use App\Http\Controllers\SuperAdmin\StudentRecordController;
use App\Http\Controllers\SuperAdmin\UserManagementController;
use App\Http\Controllers\SuperAdmin\Year_levelsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * API Routes
 * This file defines the API routes for the application.
 * It includes public routes for registration and login,
 * as well as protected routes that require authentication.
 */
Route::get('/', function () {
    return response()->json(['message' => 'API IS WORKING']);
});

/**
 * Public routes
 * These routes are accessible without authentication.
 */
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/**
 * Protected routes
 * These routes require authentication and are protected by Sanctum.
 * Users must be authenticated to access these routes.
 * The 'auth:sanctum' middleware checks for a valid token.
 * After logging in, users will receive a token that they can use to access these routes.
 * The 'role' middleware checks the user's role to restrict access to certain routes.
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

    /**
     * Super admin routes
     * These routes are accessible only to users with the 'super_admin' role.
     */
    Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
        // Super admin only routes
        Route::get('/admin/dashboard', function () {
            return response()->json(['message' => 'Super Admin Dashboard']);
        });

        Route::prefix('admin')->group(function () {
            // Academic Year
            Route::controller(AcademicYearController::class)->group(function () {
                Route::get('/academic-years', 'index');
                Route::post('/academic-years', 'store');
                Route::patch('/academic-years/{id}', 'update');
                Route::delete('/academic-years/{id}', 'destroy');
            });
            
            // Year Level Management
            Route::controller(Year_levelsController::class)->group(function () {
                Route::get('/year-level', 'index');
                Route::post('/year-level', 'store');
                Route::patch('/year-level/{id}', 'update');
                Route::delete('/year-level/{id}', 'delete');
            });
            
            //Section Management
            Route::controller(SectionController::class)->group(function () {
                Route::get('/section', 'index');
                Route::post('/section', 'store');
                Route::patch('/section/{id}', 'update');
                Route::delete('/section/{id}', 'delete');
            });
            
            /**
             * Enrollment Management
             */
            Route::controller(EnrollmentController::class)->group(function () {
                Route::get('/enrollments', 'index');
                Route::get('/students', 'index');
                Route::post('/enrollments', 'store');
                Route::post('/enrollments/bulk-store', 'bulkStore');
                Route::get('/enrollments/{id}', 'show');
                Route::put('/enrollments/{id}', 'update');
                Route::delete('/enrollments/{id}', 'destroy');
                Route::post('/enrollments/promote', 'promote');
            });

            // Teacher Management
            Route::controller(TeacherController::class)->group(function () {
                Route::get('/teacher/filter-options', 'getFilterOptions');
                Route::get('/teacher', action: 'index');
                Route::post('/teacher', 'store');
                Route::put('/teacher/{id}', 'update');
                Route::delete('/teacher/{id}', 'delete');
                Route::post('/teacher/schedule', 'createTeacherSchedule');
                Route::post('/teacher/assign-adviser', 'assignTeacherSectionAdviser');
            });

            // Student Record Management
            // Route::controller(StudentRecordController::class)->group(function () {
            //     Route::get('/records', 'getStudentRecord');
            //     Route::delete('/student/{id}', 'deleteStudent');
            //     Route::put('/student/{id}', 'updateStudent');
            // });


            Route::controller(AcademicCalendarController::class)->group(function () {
                Route::get('/academic-calendar', 'index');
                Route::post('/academic-calendar', 'bulkStore');
                Route::get('/academic-calendar/{id}', 'show');
                Route::put('/academic-calendar/{id}', 'update');
                Route::delete('/academic-calendar/{id}', 'destroy');
                Route::get('/academic-calendar/year/{academic_year_id}', 'getByYear');
            });

        });
    });

    /**
     * Teacher routes
     * These routes are accessible only to users with the 'teacher' role.
     */
    Route::middleware('role:teacher')->prefix('/teacher')->group(function () {
        // Teacher only routes
        Route::get('/dashboard', [AdminDashboardController::class, 'dashboardData']);

        /**
         * Controller for students access request
         */
        Route::controller(StudentApprovalController::class)->group(function () {
            Route::get('/students/approved', 'index');
            Route::get('/students/pending', 'pending');
            Route::get('/students/rejected', 'rejected');
            Route::put('/student-requests/{id}/approve', 'approvedStudents');
            Route::put('/student-requests/{id}/reject', 'rejectApproval');
        });

        /**
         * Controller for attendance management
         */
        Route::controller(AttendanceController::class)->group(function () {
            // Schedule Management
            Route::prefix('/schedule')->group(function () {
                Route::get('/weekly', 'getWeeklySchedule');
                Route::get('/attendance', 'getScheduleAttendance');
                Route::get('/{scheduleId}/students', 'getScheduleStudents');
                Route::get('/{scheduleId}/attendance-history', 'getAttendanceHistory');
            });

            // Attendance Management
            Route::prefix('/attendance')->group(function () {
                Route::post('/update-individual', 'updateIndividualAttendance');
                Route::post('/update-bulk', 'updateBulkAttendance');
                Route::post('/update-all', 'updateAllStudentsAttendance');
            });

            // Student-specific routes
            Route::prefix('/student')->group(function () {
                Route::get('/{studentId}/schedule/{scheduleId}/attendance-history', 'getStudentAttendanceHistory');
            });
        });

        /**
         * Controller for Monthly Attendance
         */

        Route::controller(MonthlyAttendanceController::class)->group(function () {
            Route::get('/sections/{sectionId}/monthly-attendance', 'getMonthlyAttendanceSummary');
        });


        Route::get('/sections/{sectionId}/attendance/quarterly/pdf', [AttendancePDFController::class, 'exportQuarterlyAttendancePDF']);

        Route::controller(AcademicRecordsController::class)->group(function () {
            Route::get('/academic-records/filter-options', 'getFilterOptions');
            Route::get('/academic-records/students-grade', 'getStudentsGrade');
            Route::get('/academic-records/statistics', 'getGradeStatistics');
            Route::put('/academic-records/update-grade', 'updateGrade');
        });

        Route::controller(PromotionReportController::class)->group(function () {
            Route::get('/promotion-reports/statistics', 'getPromotionReportStatistics');
            Route::get('/promotion-reports/filters', 'getPromotionFilterOptions');
        });

        Route::get('/book-management/filter-options', [BookManagementController::class, 'getFilterOptions']);
        Route::post('/book-management/distribute-books', [BookManagementController::class, 'distributeBooks']);
        Route::put('/book-management/return-book/{id}', [BookManagementController::class, 'returnBook']);
        Route::apiResource('/book-management', BookManagementController::class);

        Route::controller(WorkloadManagementController::class)->prefix('/workload')->group(function () {
            Route::get('/', 'index');
        });

        Route::controller(CertificateController::class)->prefix('/certificate')->group(function () {
            Route::get('/', 'index');
            Route::get('/preview/{type}/{studentId}/{quarterId?}', 'preview');
            Route::get('/download/{type}/{studentId}/{quarterId?}', 'download');
            Route::get('/print-all', 'printAll');
            Route::get('/honor-roll/filter', 'filterHonorRoll');
        });

        Route::controller(ParentsConferenceController::class)->prefix('/parents-conference')->group(function () {
            Route::get('/dashboard', 'index');
            Route::get('/student-data/{studentId}', 'showStudentProfile');
            Route::get('/print-student-card/{studentId}', 'printStudentReportCard');
            Route::get('/print-all-student-cards', 'printAllStudentReportCards');
        });

        Route::apiResource('/student-bmi', StudentBmiController::class);
    });

    /**
     * Student routes
     * These routes are accessible only to users with the 'student' role.
     */
    Route::middleware('role:student')->prefix('/student')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/student-grade', [GradeController::class, 'getStudentGrade']);
        Route::get('/student-grade/filter', [GradeController::class, 'quarterFilter']);
        Route::get('/student-attendance', [StudentAttendanceController::class, 'attendanceRecords']);
        Route::get('/student-attendance/filter', [StudentAttendanceController::class, 'attendanceMonthFilter']);
        Route::get('/health-profile', [HealthProfileController::class, 'getHealthProfileData']);
        Route::get('/certificates', [AchievementsController::class, 'getCertificates']);
        Route::get('/certificate/download', [AchievementsController::class, 'downloadCertificate']);
    });
});

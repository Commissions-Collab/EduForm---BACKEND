<?php

use App\Http\Controllers\Admin\AcademicRecordsController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendancePDFController;
use App\Http\Controllers\Admin\BookManagementController;
use App\Http\Controllers\Admin\CertificateController;
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
<<<<<<< HEAD
use App\Http\Controllers\Student\AchievementsController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\GradeController;
use App\Http\Controllers\Student\HealthProfileController;
use App\Http\Controllers\Student\StudentAttendanceController;
=======
use App\Http\Controllers\SuperAdmin\AcademicYearController;
use App\Http\Controllers\SuperAdmin\EnrollmentController;
use App\Http\Controllers\SuperAdmin\StudentController;
>>>>>>> 76be60adbbfe1417f06f65ca7f410753532916c8
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

            // Student Record Management
            Route::controller(StudentController::class)->group(function () {
                Route::get('/records', 'getStudentRecord');
                Route::delete('/student/{id}', 'deleteStudent');
                Route::put('/student/{id}', 'updateStudent');
            });

            // Teacher Management
            Route::controller(TeacherController::class)->group(function () {
                Route::post('/teacher', 'create');
                Route::put('/teachers/{id}/record', 'update'); 
                Route::delete('/teachers/{id}', 'delete');
            });

            // Year Level Management
            Route::controller(Year_levelsController::class)->group(function () {
                Route::post('/year_level', 'createYearLevel');
                Route::delete('/year_level/{id}', 'deleteYearLevel');
            });

            // Schedule Management
            Route::controller(ScheduleController::class)->group(function () {
                Route::post('/schedule', 'createTeacherSchedule');
                Route::put('/schedule/{id}', 'upateTeacherSchedule');
            });


            //Section
            Route::controller(SectionController::class)->group(function () {
                        Route::post('/section', 'createSections');
                        Route::put('/section/{id}', 'updateSection');
                        Route::delete('/section/{id}', 'deleteSection');
                    });

            // Academic Year
            Route::controller(AcademicYearController::class)->group(function () {
                Route::get('/academic-years', 'index');
                Route::post('/academic-years', 'store');
                Route::get('/academic-years/{id}', 'show');
                Route::put('/academic-years/{id}', 'update');
                Route::delete('/academic-years/{id}', 'destroy');
                Route::get('/academic-years-current', 'current');
            });

            Route::controller(EnrollmentController::class)->group(function () {
                Route::get('/enrollments', 'index');
                Route::post('/enrollments', 'store');
                Route::get('/enrollments/{id}', 'show');
                Route::put('/enrollments/{id}', 'update');
                Route::delete('/enrollments/{id}', 'destroy');
            });

        });
     
    });

    /**
     * Teacher routes
     * These routes are accessible only to users with the 'teacher' role.
     */
    Route::middleware('role:teacher')->prefix('/teacher')->group(function () {
        // Teacher only routes
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Teacher Dashboard']);
        });

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
            Route::get('/schedule/weekly', 'getWeeklySchedule');
            Route::get('/schedule/{scheduleId}/students', 'getScheduleStudents');
            Route::post('/attendance/update-individual', 'updateIndividualAttendance');
            Route::post('/attendance/update-bulk', 'updateBulkAttendance');
            Route::post('/attendance/update-all', 'updateAllStudentsAttendance');
            Route::get('/schedule/{scheduleId}/attendance-history', 'getAttendanceHistory');
            Route::get('/student/{studentId}/schedule/{scheduleId}/attendance-history', 'getStudentAttendanceHistory');
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

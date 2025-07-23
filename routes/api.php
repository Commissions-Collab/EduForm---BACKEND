<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\StudentApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SuperAdmin\ScheduleController;
use App\Http\Controllers\SuperAdmin\SectionController;
use App\Http\Controllers\SuperAdmin\TeacherController;
use App\Http\Controllers\SuperAdmin\UserManagementController;
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
        //display
        Route::get('/admin/records', [UserManagementController::class, 'getStudentRecord']);

        Route::post('/admin/schedule', [ScheduleController::class, 'createTeacherSchedule']);
        Route::post('/admin/section', [SectionController::class, 'createSections']);
        Route::post('/admin/teacher', [TeacherController::class, 'teacherRegistration']);

        //update 
        Route::put('/admin/teachers/{id}record', [TeacherController::class, 'updateTeacherRecord']);
        Route::put('/admin/teachers/{id}/advisory', [TeacherController::class, 'updateClassAdvisory']);
        Route::put('/admin/teachers/{id}/subject', [TeacherController::class, 'updateTeachersSubject']);
        Route::put('/admin/student/{id}', [UserManagementController::class, 'updateStudent']);
        //delete
        Route::delete('/admin/teachers/{id}', [TeacherController::class, 'deleteTeacher']);
        Route::delete('/admin/student/{id}', [UserManagementController::class, 'deleteStudent']);
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
            Route::get('/subjects', 'getTeacherSubjects');
            Route::post('/students', 'getStudentsForAttendance');
            Route::post('/attendance/update', 'updateAttendace');
        });
    });

    /**
     * Student routes
     * These routes are accessible only to users with the 'student' role.
     */
    Route::middleware('role:student')->group(function () {
        // Student only routes
        Route::get('/student/dashboard', function () {
            return response()->json(['message' => 'Student Dashboard']);
        });
    });
});

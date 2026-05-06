<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->post('/logout', [AuthController::class, 'logout']);
Route::middleware(['auth:sanctum'])->post('/change-password', [AuthController::class, 'changePassword']);

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('/overview', [AdminController::class, 'overview']);
    Route::get('/teachers', [AdminController::class, 'teachers']);
    Route::post('/teachers', [AdminController::class, 'createTeacher']);
    Route::put('/teachers/{teacher}', [AdminController::class, 'updateTeacher']);
    Route::delete('/teachers/{teacher}', [AdminController::class, 'deleteTeacher']);
    Route::get('/students', [AdminController::class, 'students']);
    Route::post('/students', [AdminController::class, 'createStudent']);
    Route::put('/students/{student}', [AdminController::class, 'updateStudent']);
    Route::delete('/students/{student}', [AdminController::class, 'deleteStudent']);
    Route::get('/subjects', [AdminController::class, 'subjects']);
    Route::post('/subjects', [AdminController::class, 'createSubject']);
    Route::put('/subjects/{subject}', [AdminController::class, 'updateSubject']);
    Route::delete('/subjects/{subject}', [AdminController::class, 'deleteSubject']);
    Route::get('/sections', [AdminController::class, 'sections']);
    Route::post('/sections', [AdminController::class, 'createSection']);
    Route::get('/sections/{section}', [AdminController::class, 'showSection']);
    Route::put('/sections/{section}', [AdminController::class, 'updateSection']);
    Route::delete('/sections/{section}', [AdminController::class, 'deleteSection']);
    Route::post('/teacher-subjects', [AdminController::class, 'assignTeacher']);
    Route::post('/student-sections', [AdminController::class, 'assignStudent']);
});

Route::middleware(['auth:sanctum', 'role:admin,teacher'])->group(function (): void {
    Route::get('/dashboard/summary', [DashboardController::class, 'getSummary']);
    Route::get('/dashboard/students', [DashboardController::class, 'getStudents']);
    Route::get('/dashboard/slow-learners', [DashboardController::class, 'getSlowLearners']);
    Route::get('/dashboard/analytics', [DashboardController::class, 'getAnalytics']);
    Route::get('/dashboard/students/{student}', [DashboardController::class, 'getStudentDetails']);
    Route::get('/dashboard/students/{student}/performance-trends', [DashboardController::class, 'getPerformanceTrends']);
    Route::get('/notifications', [DashboardController::class, 'getNotifications']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->get('/dashboard/recommendations', [DashboardController::class, 'getRecommendations']);

Route::middleware(['auth:sanctum', 'role:teacher'])->prefix('teacher')->group(function (): void {
    Route::get('/sections', [TeacherController::class, 'getAssignedSections']);
    Route::get('/students', [TeacherController::class, 'getStudents']);
    Route::get('/subject-performance', [TeacherController::class, 'getSubjectPerformance']);
    Route::get('/students/{student}/performance', [TeacherController::class, 'getStudentPerformance']);
    Route::post('/students/{student}/remedial-actions', [TeacherController::class, 'addRemedialAction']);
});

Route::middleware(['auth:sanctum', 'role:student'])->get('/student/me', [StudentController::class, 'getMyData']);

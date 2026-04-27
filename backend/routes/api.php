<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->post('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum', 'role:admin,teacher'])->group(function (): void {
    Route::get('/dashboard/summary', [DashboardController::class, 'getSummary']);
    Route::get('/dashboard/slow-learners', [DashboardController::class, 'getSlowLearners']);
    Route::get('/dashboard/recommendations', [DashboardController::class, 'getRecommendations']);
});

Route::middleware(['auth:sanctum', 'role:student'])->get('/student/me', [StudentController::class, 'getMyData']);

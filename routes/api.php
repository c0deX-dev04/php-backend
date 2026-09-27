<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\LabAssignmentController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\SubjectController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', DashboardController::class);

    Route::apiResource('subjects', SubjectController::class)->only(['index', 'show']);

    Route::post('/attendance/sync', [AttendanceController::class, 'sync'])->middleware('role:cr');
    Route::apiResource('attendance', AttendanceController::class)->only(['index', 'show']);
    Route::apiResource('attendance', AttendanceController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:cr');

    Route::get('/notes/{note}/download', [NoteController::class, 'download']);
    Route::apiResource('notes', NoteController::class)->only(['index', 'show']);
    Route::apiResource('notes', NoteController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:cr');

    Route::apiResource('labs', LabController::class)->only(['index', 'show']);
    Route::apiResource('labs', LabController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:cr');

    Route::apiResource('lab-assignments', LabAssignmentController::class)->only(['index', 'show']);
    Route::apiResource('lab-assignments', LabAssignmentController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:cr');

    Route::apiResource('exams', ExamController::class)->only(['index', 'show']);
    Route::apiResource('exams', ExamController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:cr');

    Route::get('/notices/{notice}/download', [NoticeController::class, 'download']);
    Route::apiResource('notices', NoticeController::class)->only(['index', 'show']);
    Route::apiResource('notices', NoticeController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:cr');
});

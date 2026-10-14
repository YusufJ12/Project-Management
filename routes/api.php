<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\SpaceApiController;
use App\Http\Controllers\Api\v1\TaskApiController;
use App\Http\Controllers\Api\v1\WorkspaceApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (NexSphere Mobile & Third-Party Integrations)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public Authentication Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });

    // Protected Endpoints via Laravel Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        // Authenticated User Session
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // Dashboard Metrics
        Route::get('/dashboard', [WorkspaceApiController::class, 'dashboard']);

        // Workspaces CRUD
        Route::get('/workspaces', [WorkspaceApiController::class, 'index']);
        Route::post('/workspaces', [WorkspaceApiController::class, 'store']);
        Route::put('/workspaces/{workspace}', [WorkspaceApiController::class, 'update']);
        Route::delete('/workspaces/{workspace}', [WorkspaceApiController::class, 'destroy']);
        Route::get('/workspaces/{workspace}/spaces', [WorkspaceApiController::class, 'spaces']);
        Route::post('/workspaces/{workspace}/spaces', [SpaceApiController::class, 'store']);

        // Spaces (Projects) CRUD & Timeline
        Route::get('/spaces/{space}', [SpaceApiController::class, 'show']);
        Route::put('/spaces/{space}', [SpaceApiController::class, 'update']);
        Route::delete('/spaces/{space}', [SpaceApiController::class, 'destroy']);
        Route::get('/spaces/{space}/timeline', [SpaceApiController::class, 'timeline']);

        // My Tasks (Assigned to Me)
        Route::get('/my-tasks', [TaskApiController::class, 'myTasks']);

        // Tasks & Kanban Board
        Route::post('/tasks', [TaskApiController::class, 'store']);
        Route::get('/tasks/{task}', [TaskApiController::class, 'show']);
        Route::put('/tasks/{task}', [TaskApiController::class, 'update']);
        Route::post('/tasks/{task}/move', [TaskApiController::class, 'move']);
        Route::delete('/tasks/{task}', [TaskApiController::class, 'destroy']);

        // Checklists Subtasks
        Route::post('/tasks/{task}/checklists', [TaskApiController::class, 'addChecklist']);
        Route::post('/tasks/{task}/checklists/{checklist}/toggle', [TaskApiController::class, 'toggleChecklist']);

        // Comments
        Route::post('/tasks/{task}/comments', [TaskApiController::class, 'addComment']);

        // Activity Logs
        Route::get('/activity-logs', [WorkspaceApiController::class, 'activityLogs']);
    });
});

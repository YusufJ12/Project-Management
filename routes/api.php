<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\DeviceTokenApiController;
use App\Http\Controllers\Api\v1\NotificationApiController;
use App\Http\Controllers\Api\v1\SpaceApiController;
use App\Http\Controllers\Api\v1\TaskApiController;
use App\Http\Controllers\Api\v1\WorkspaceApiController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (NexSphere Mobile & Third-Party Integrations)
|--------------------------------------------------------------------------
*/

// Broadcasting authentication route for Sanctum mobile API
Broadcast::routes(['prefix' => 'v1', 'middleware' => ['auth:sanctum']]);
Broadcast::routes(['prefix' => 'api/v1', 'middleware' => ['auth:sanctum']]);

Route::prefix('v1')->group(function () {
    // Public Authentication Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
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
        Route::delete('/tasks/{task}/checklists/{checklist}', [TaskApiController::class, 'deleteChecklist']);

        // Comments
        Route::post('/tasks/{task}/comments', [TaskApiController::class, 'addComment'])->middleware('throttle:60,1');
        Route::delete('/tasks/{task}/comments/{comment}', [TaskApiController::class, 'deleteComment']);

        // Task Attachments
        Route::post('/tasks/{task}/attachments', [\App\Http\Controllers\TaskAttachmentController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/tasks/{task}/attachments/{attachment}/download', [\App\Http\Controllers\TaskAttachmentController::class, 'download']);
        Route::get('/tasks/{task}/attachments/{attachment}/preview', [\App\Http\Controllers\TaskAttachmentController::class, 'preview']);
        Route::delete('/tasks/{task}/attachments/{attachment}', [\App\Http\Controllers\TaskAttachmentController::class, 'destroy']);

        // Activity Logs
        Route::get('/activity-logs', [WorkspaceApiController::class, 'activityLogs']);

        // Notifications
        Route::get('/notifications', [NotificationApiController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationApiController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/read', [NotificationApiController::class, 'markAsRead']);

        // User Device Tokens (FCM)
        Route::post('/devices/token', [DeviceTokenApiController::class, 'store']);
        Route::delete('/devices/token', [DeviceTokenApiController::class, 'destroy']);
    });
});

<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (NexSphere Project Management Platform)
|--------------------------------------------------------------------------
*/

// Root URL: Arahkan ke dashboard jika sudah login, atau ke login jika belum
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

// Guest Routes (Autentikasi)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.post');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Workspace Management
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::post('/workspaces/{workspace}/members', [WorkspaceController::class, 'addMember'])->name('workspaces.members.store');
    Route::put('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'updateMemberRole'])->name('workspaces.members.update');
    Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])->name('workspaces.members.destroy');

    // Space & Project Management
    Route::get('/spaces', [SpaceController::class, 'index'])->name('spaces.index');
    Route::post('/spaces', [SpaceController::class, 'store'])->name('spaces.store');
    Route::get('/spaces/{space:slug}', [SpaceController::class, 'show'])->name('spaces.show');
    Route::get('/spaces/{space:slug}/sync', [SpaceController::class, 'sync'])->name('spaces.sync');
    Route::put('/spaces/{space}', [SpaceController::class, 'update'])->name('spaces.update');
    Route::delete('/spaces/{space}', [SpaceController::class, 'destroy'])->name('spaces.destroy');
    Route::post('/spaces/{space}/members', [SpaceController::class, 'addMember'])->name('spaces.members.store');
    Route::put('/spaces/{space}/members/{targetUser}', [SpaceController::class, 'updateMemberRole'])->name('spaces.members.update');
    Route::delete('/spaces/{space}/members/{targetUser}', [SpaceController::class, 'removeMember'])->name('spaces.members.destroy');

    // Task Engine & Kanban Drag-and-Drop
    Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/move', [\App\Http\Controllers\TaskController::class, 'move'])->name('tasks.move');
    Route::delete('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('tasks.destroy');

    // Task Checklists
    Route::post('/tasks/{task}/checklists', [\App\Http\Controllers\TaskChecklistController::class, 'store'])->name('tasks.checklists.store');
    Route::post('/tasks/{task}/checklists/{checklist}/toggle', [\App\Http\Controllers\TaskChecklistController::class, 'toggle'])->name('tasks.checklists.toggle');
    Route::delete('/tasks/{task}/checklists/{checklist}', [\App\Http\Controllers\TaskChecklistController::class, 'destroy'])->name('tasks.checklists.destroy');

    // Task Comments
    Route::get('/tasks/{task}/comments', [\App\Http\Controllers\TaskCommentController::class, 'index'])->name('tasks.comments.index');
    Route::post('/tasks/{task}/comments', [\App\Http\Controllers\TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('/tasks/{task}/comments/{comment}', [\App\Http\Controllers\TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Task Attachments
    Route::post('/tasks/{task}/attachments', [\App\Http\Controllers\TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::get('/tasks/{task}/attachments/{attachment}/download', [\App\Http\Controllers\TaskAttachmentController::class, 'download'])->name('tasks.attachments.download');
    Route::get('/tasks/{task}/attachments/{attachment}/preview', [\App\Http\Controllers\TaskAttachmentController::class, 'preview'])->name('tasks.attachments.preview');
    Route::delete('/tasks/{task}/attachments/{attachment}', [\App\Http\Controllers\TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // My Tasks Module
    Route::get('/my-tasks', [\App\Http\Controllers\MyTaskController::class, 'index'])->name('my-tasks.index');

    // Activity Logs Module
    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');

    // User Profile Management
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');

    // Super Admin & RBAC Management
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/role', [\App\Http\Controllers\UserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
});

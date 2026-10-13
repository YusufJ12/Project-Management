<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20 MB
        ]);

        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $sizeBytes = $file->getSize();
        $mime = $file->getMimeType();
        $checksum = md5_file($file->getRealPath());

        $storagePath = $file->store("task_attachments/{$task->id}", 'public');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $user->id,
            'original_name' => $originalName,
            'storage_path' => $storagePath,
            'size_bytes' => $sizeBytes,
            'detected_mime' => $mime,
            'checksum' => $checksum,
        ]);

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => 'attachment_uploaded',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'task_title' => $task->title,
                'file_name' => $originalName,
                'file_size' => $sizeBytes,
            ],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Berkas berhasil diunggah.',
                'attachment' => $attachment->load('uploader:id,name,email'),
            ], 201);
        }

        return back()->with('success', 'Berkas berhasil diunggah.');
    }

    public function download(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse|JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() || $attachment->task_id !== $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        if (! Storage::disk('public')->exists($attachment->storage_path)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Berkas tidak ditemukan di server.'], 404);
            }
            return back()->with('error', 'Berkas fisik tidak ditemukan di server.');
        }

        return Storage::disk('public')->download($attachment->storage_path, $attachment->original_name);
    }

    public function destroy(Request $request, Task $task, TaskAttachment $attachment): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space');
        $space = $task->list->space;

        if (! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() || $attachment->task_id !== $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        $fileName = $attachment->original_name;

        if (Storage::disk('public')->exists($attachment->storage_path)) {
            Storage::disk('public')->delete($attachment->storage_path);
        }

        $attachment->delete();

        ActivityLog::create([
            'workspace_id' => $space->workspace_id,
            'space_id' => $space->id,
            'list_id' => $task->list_id,
            'actor_id' => $user->id,
            'action' => 'attachment_deleted',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => [
                'task_title' => $task->title,
                'file_name' => $fileName,
            ],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Berkas lampiran berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'Berkas lampiran berhasil dihapus.');
    }
}


<?php

namespace App\Http\Controllers;

use App\Events\TaskAttachmentDeletedEvent;
use App\Events\TaskAttachmentUploadedEvent;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    /**
     * Allowed file extensions and MIME types for secure private storage.
     */
    protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx', 'xlsx', 'txt', 'zip'];

    protected array $dangerousExtensions = [
        'php', 'phtml', 'phar', 'exe', 'sh', 'svg', 'html', 'htm', 'js',
        'bat', 'cmd', 'ps1', 'vbs', 'cgi', 'pl', 'py', 'jar', 'apk',
    ];

    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream', // sometimes zip or docx detected as octet-stream
    ];

    public function store(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20 MB
        ]);

        $user = Auth::user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        if (! $user->isSuperAdmin() && ! $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                    'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                ], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $file = $request->file('file');
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));

        // Validasi ekstensi terlarang (script / executable)
        if (in_array($extension, $this->dangerousExtensions, true)) {
            $msg = 'Ekstensi berkas berpotensi berbahaya dan ditolak oleh sistem keamanan.';
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['file' => [$msg]]], 422);
            }
            return back()->withErrors(['file' => $msg]);
        }

        // Validasi allowlist ekstensi
        if (! in_array($extension, $this->allowedExtensions, true)) {
            $msg = 'Format berkas tidak diizinkan. Hanya berkas jpg, jpeg, png, webp, pdf, docx, xlsx, txt, zip yang diperbolehkan.';
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['file' => [$msg]]], 422);
            }
            return back()->withErrors(['file' => $msg]);
        }

        // Validasi MIME type
        $mime = $file->getMimeType();
        if ($mime && ! in_array($mime, $this->allowedMimeTypes, true)) {
            $msg = 'Tipe MIME berkas tidak sesuai atau tidak didukung.';
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['file' => [$msg]]], 422);
            }
            return back()->withErrors(['file' => $msg]);
        }

        // Sanitasi nama asli berkas untuk label DB
        $rawOriginalName = basename($file->getClientOriginalName());
        $sanitizedOriginalName = preg_replace('/[^\w\s\.-]/', '_', $rawOriginalName);
        if (empty($sanitizedOriginalName)) {
            $sanitizedOriginalName = 'attachment_' . Str::random(8) . '.' . $extension;
        }

        // Simpan ke disk lokal privat (bukan public disk) dengan nama UUID acak
        $storageFilename = Str::uuid()->toString() . '.' . $extension;
        $storagePath = $file->storeAs("task_attachments/{$task->id}", $storageFilename, 'local');

        $sizeBytes = $file->getSize();
        $checksum = hash_file('sha256', $file->getRealPath());

        $attachment = $task->attachments()->create([
            'uploaded_by' => $user->id,
            'original_name' => $sanitizedOriginalName,
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
                'file_name' => $sanitizedOriginalName,
                'file_size' => $sizeBytes,
            ],
        ]);

        $attachment->load('uploader:id,name,email');

        DB::afterCommit(function () use ($space, $task, $attachment) {
            try {
                broadcast(new TaskAttachmentUploadedEvent($space->id, $task->id, $attachment));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
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
        $task->load('list.space.workspace');
        $space = $task->list->space;

        $hasAccess = $user && (
            $user->isSuperAdmin() ||
            $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() ||
            $space->members()->where('users.id', $user->id)->exists()
        );

        if (! $hasAccess || (int) $attachment->task_id !== (int) $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
            }
            abort(403, 'Akses ditolak.');
        }

        // Cari di disk local privat terlebih dahulu, fallback ke public jika ada data lama
        $disk = Storage::disk('local')->exists($attachment->storage_path)
            ? 'local'
            : (Storage::disk('public')->exists($attachment->storage_path) ? 'public' : null);

        if (! $disk) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Berkas fisik tidak ditemukan di server.'], 404);
            }
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        return Storage::disk($disk)->download(
            $attachment->storage_path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->detected_mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function preview(Request $request, Task $task, TaskAttachment $attachment): BinaryFileResponse
    {
        $user = Auth::user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        $hasAccess = $user && (
            $user->isSuperAdmin() ||
            $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() ||
            $space->members()->where('users.id', $user->id)->exists()
        );

        if (! $hasAccess || (int) $attachment->task_id !== (int) $task->id) {
            abort(403, 'Akses ditolak.');
        }

        $disk = Storage::disk('local')->exists($attachment->storage_path)
            ? 'local'
            : (Storage::disk('public')->exists($attachment->storage_path) ? 'public' : null);

        if (! $disk) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $filePath = Storage::disk($disk)->path($attachment->storage_path);
        $mime = $attachment->detected_mime ?: Storage::disk($disk)->mimeType($attachment->storage_path);

        return response()->file($filePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($attachment->original_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Task $task, TaskAttachment $attachment): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $task->load('list.space.workspace');
        $space = $task->list->space;

        $hasAccess = $user && (
            $user->isSuperAdmin() ||
            $user->workspaces()->where('workspaces.id', $space->workspace_id)->exists() ||
            $space->members()->where('users.id', $user->id)->exists()
        );

        if (! $hasAccess || (int) $attachment->task_id !== (int) $task->id) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
            }
            return back()->with('error', 'Akses ditolak.');
        }

        if ($user->isViewerInSpace($space)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                    'error' => 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.',
                ], 403);
            }
            return back()->with('error', 'Peran Viewer hanya memiliki hak akses untuk melihat dan berdiskusi.');
        }

        $fileName = $attachment->original_name;

        if (Storage::disk('local')->exists($attachment->storage_path)) {
            Storage::disk('local')->delete($attachment->storage_path);
        }

        if (Storage::disk('public')->exists($attachment->storage_path)) {
            Storage::disk('public')->delete($attachment->storage_path);
        }

        $attachmentId = (int) $attachment->id;

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

        DB::afterCommit(function () use ($space, $task, $attachmentId, $user) {
            try {
                broadcast(new TaskAttachmentDeletedEvent($space->id, $task->id, $attachmentId, $user));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Berkas lampiran berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'Berkas lampiran berhasil dihapus.');
    }
}

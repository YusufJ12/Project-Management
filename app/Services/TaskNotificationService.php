<?php

namespace App\Services;

use App\Mail\TaskCompletedMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskNotificationService
{
    /**
     * Send email notification to project owner (space managers or workspace owner)
     * when a task moves/updates to a completed (is_done = true) stage.
     * Wrapped in try/catch so SMTP failures on shared hosting never crash the request.
     */
    public static function notifyTaskCompleted(Task $task, ?User $actor = null): void
    {
        try {
            $task->loadMissing('list.space.workspace.owner', 'stage');
            $space = $task->list?->space;

            if (! $space) {
                return;
            }

            // Find project owner (Space managers or Workspace owner)
            $recipients = $space->members()->wherePivot('role', 'manager')->get();

            if ($recipients->isEmpty()) {
                $workspaceOwner = $space->workspace?->owner ?? User::find($space->workspace?->owner_id);
                if ($workspaceOwner) {
                    $recipients = collect([$workspaceOwner]);
                }
            }

            $validRecipients = $recipients->filter(fn ($user) => ! empty($user->email))->unique('id');

            foreach ($validRecipients as $recipient) {
                try {
                    Mail::to($recipient->email)->send(new TaskCompletedMail($task, $actor));
                } catch (\Throwable $mailError) {
                    Log::warning("Gagal mengirim email task completed ke {$recipient->email}: " . $mailError->getMessage(), [
                        'task_id' => $task->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $mailError,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error saat memproses notifikasi task completed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'exception' => $e,
            ]);
        }
    }
}

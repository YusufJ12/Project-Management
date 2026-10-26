<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'due_at' => 'date:Y-m-d',
            'archived_at' => 'datetime',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TaskStage::class, 'stage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_at');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    /**
     * Cascade timeline changes to dependent tasks recursively with cycle detection.
     */
    public function cascadeTimeline(array &$visited = []): void
    {
        if (isset($visited[$this->id])) {
            return;
        }
        $visited[$this->id] = true;

        $prereqEnd = $this->due_at ?? $this->start_date;
        if (! $prereqEnd) {
            return;
        }

        $dependents = $this->dependents()->get();

        foreach ($dependents as $dependent) {
            if (isset($visited[$dependent->id])) {
                continue;
            }

            // Find the latest finish date of all prerequisites for this dependent
            $allPrereqs = $dependent->dependencies()->get();
            $latestPrereqFinish = null;
            foreach ($allPrereqs as $prereq) {
                $pEnd = $prereq->due_at ?? $prereq->start_date;
                if ($pEnd) {
                    $pEndDate = \Carbon\Carbon::parse($pEnd)->startOfDay();
                    if ($latestPrereqFinish === null || $pEndDate->gt($latestPrereqFinish)) {
                        $latestPrereqFinish = $pEndDate;
                    }
                }
            }

            if (! $latestPrereqFinish) {
                continue;
            }

            $minStart = $latestPrereqFinish->copy()->addDay();

            $depStart = $dependent->start_date ? \Carbon\Carbon::parse($dependent->start_date)->startOfDay() : null;
            $depDue = $dependent->due_at ? \Carbon\Carbon::parse($dependent->due_at)->startOfDay() : null;

            // Shift if dependent does not start after the prerequisite finishes
            if (! $depStart || $depStart->lte($latestPrereqFinish)) {
                $duration = ($depStart && $depDue) ? $depStart->diffInDays($depDue) : null;

                $newStart = $minStart;
                $newDue = $duration !== null
                    ? $newStart->copy()->addDays($duration)
                    : ($depDue && $depDue->lt($newStart) ? $newStart->copy() : $depDue);

                $dependent->update([
                    'start_date' => $newStart->toDateString(),
                    'due_at' => $newDue ? $newDue->toDateString() : null,
                ]);

                // Recursively cascade to dependent's dependents
                $dependent->cascadeTimeline($visited);
            }
        }
    }

    /**
     * Adjust this task's dates so that it starts after all its prerequisites finish.
     * Returns true if dates were modified.
     */
    public function adjustDatesToDependencies(): bool
    {
        $allPrereqs = $this->dependencies()->get();
        if ($allPrereqs->isEmpty()) {
            return false;
        }

        $latestPrereqFinish = null;
        foreach ($allPrereqs as $prereq) {
            $pEnd = $prereq->due_at ?? $prereq->start_date;
            if ($pEnd) {
                $pEndDate = \Carbon\Carbon::parse($pEnd)->startOfDay();
                if ($latestPrereqFinish === null || $pEndDate->gt($latestPrereqFinish)) {
                    $latestPrereqFinish = $pEndDate;
                }
            }
        }

        if (! $latestPrereqFinish) {
            return false;
        }

        $minStart = $latestPrereqFinish->copy()->addDay();
        $curStart = $this->start_date ? \Carbon\Carbon::parse($this->start_date)->startOfDay() : null;
        $curDue = $this->due_at ? \Carbon\Carbon::parse($this->due_at)->startOfDay() : null;

        if (! $curStart || $curStart->lte($latestPrereqFinish)) {
            $duration = ($curStart && $curDue) ? $curStart->diffInDays($curDue) : null;
            $newStart = $minStart;
            $newDue = $duration !== null
                ? $newStart->copy()->addDays($duration)
                : ($curDue && $curDue->lt($newStart) ? $newStart->copy() : $curDue);

            $this->update([
                'start_date' => $newStart->toDateString(),
                'due_at' => $newDue ? $newDue->toDateString() : null,
            ]);

            return true;
        }

        return false;
    }
}


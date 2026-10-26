<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['description'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function getDescriptionAttribute(): string
    {
        if (! empty($this->metadata['description'])) {
            return $this->metadata['description'];
        }

        $actorName = $this->actor?->name ?? 'Sistem';
        $title = $this->metadata['title'] ?? 'item';

        return match ($this->action) {
            'task_created' => "{$actorName} membuat tugas baru: {$title}",
            'task_moved' => "{$actorName} memindahkan tugas {$title}",
            'task_updated' => "{$actorName} memperbarui rincian tugas {$title}",
            'timeline_adjusted' => "{$actorName} menyesuaikan timeline tugas {$title}" . (! empty($this->metadata['dates_summary']) ? " ({$this->metadata['dates_summary']})" : ''),
            'timeline_cascaded' => "Timeline tugas {$title} disesuaikan otomatis mengikuti prasyarat {$this->metadata['prerequisite_title']}" . (! empty($this->metadata['dates_summary']) ? " ({$this->metadata['dates_summary']})" : ''),
            'dependency_added' => "{$actorName} menambahkan dependensi tugas {$title} pada prasyarat {$this->metadata['prerequisite_title']}",
            'dependency_removed' => "{$actorName} menghapus dependensi tugas {$title}",
            'task_deleted' => "{$actorName} menghapus tugas {$title}",
            'comment_added' => "{$actorName} menambahkan komentar pada tugas {$title}",
            'checklist_added' => "{$actorName} menambahkan subtask pada tugas {$title}",
            'checklist_toggled' => "{$actorName} mengubah status subtask pada tugas {$title}",
            'attachment_uploaded' => "{$actorName} mengunggah berkas lampiran pada tugas {$title}",
            'space_created' => "{$actorName} membuat ruang kerja proyek {$title}",
            default => "{$actorName} melakukan tindakan {$this->action} pada {$title}",
        };
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskList extends Model
{
    use HasFactory;

    protected $table = 'lists';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(TaskStage::class, 'list_id')->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'list_id')->orderBy('position');
    }
}


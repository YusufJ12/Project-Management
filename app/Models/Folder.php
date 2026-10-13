<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasFactory;

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

    public function parentFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_folder_id');
    }

    public function subFolders(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_folder_id');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class);
    }
}


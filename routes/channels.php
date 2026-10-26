<?php

use App\Models\Space;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private channel for space realtime updates.
 * Authorized for:
 * - Super admin
 * - Workspace owner
 * - Workspace admin
 * - Space members
 */
Broadcast::channel('space.{spaceId}', function (User $user, int|string $spaceId) {
    if ($user->isSuperAdmin()) {
        return true;
    }

    $space = Space::with('workspace')->find($spaceId);
    if (! $space) {
        return false;
    }

    // Workspace owner
    if ($space->workspace && (int) $space->workspace->owner_id === (int) $user->id) {
        return true;
    }

    // Workspace admin / owner role
    $wsMembership = $user->workspaces()->where('workspaces.id', $space->workspace_id)->first();
    if ($wsMembership && in_array($wsMembership->pivot->role, ['owner', 'admin'], true)) {
        return true;
    }

    // Space member
    return $space->members()->where('users.id', $user->id)->exists();
});

/**
 * Private channel for workspace realtime updates.
 * Authorized for:
 * - Super admin
 * - Workspace owner
 * - Workspace members
 */
Broadcast::channel('workspace.{workspaceId}', function (User $user, int|string $workspaceId) {
    if ($user->isSuperAdmin()) {
        return true;
    }

    $workspace = Workspace::find($workspaceId);
    if (! $workspace) {
        return false;
    }

    if ((int) $workspace->owner_id === (int) $user->id) {
        return true;
    }

    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists();
});

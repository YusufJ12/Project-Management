<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $workspaces = collect();
        $currentWorkspace = null;

        if ($user) {
            $workspaces = $user->workspaces()
                ->select('workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.owner_id')
                ->get()
                ->map(fn ($ws) => [
                    'id' => $ws->id,
                    'name' => $ws->name,
                    'slug' => $ws->slug,
                    'is_owner' => $ws->owner_id === $user->id,
                    'role' => $ws->pivot->role ?? 'member',
                ]);

            $activeWorkspaceId = $request->session()->get('active_workspace_id');
            $activeWsModel = null;

            if ($activeWorkspaceId) {
                $activeWsModel = $user->workspaces()->where('workspaces.id', $activeWorkspaceId)->first();
            }

            if (! $activeWsModel && $workspaces->isNotEmpty()) {
                $activeWsModel = $user->workspaces()->first();
                if ($activeWsModel) {
                    $request->session()->put('active_workspace_id', $activeWsModel->id);
                }
            }

            if ($activeWsModel) {
                $isWsOwnerOrAdmin = $activeWsModel->owner_id === $user->id ||
                    $user->isSuperAdmin() ||
                    ($activeWsModel->pivot->role ?? 'member') === 'admin';

                $currentWorkspace = [
                    'id' => $activeWsModel->id,
                    'name' => $activeWsModel->name,
                    'slug' => $activeWsModel->slug,
                    'role' => $activeWsModel->pivot->role ?? 'member',
                    'spaces' => $activeWsModel->spaces()
                        ->whereNull('archived_at')
                        ->when(! $isWsOwnerOrAdmin, function ($query) use ($user) {
                            $query->whereHas('members', function ($m) use ($user) {
                                $m->where('users.id', $user->id);
                            });
                        })
                        ->select('spaces.id', 'spaces.name', 'spaces.slug', 'spaces.status')
                        ->get(),
                ];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username ?? null,
                    'role' => $user->role ?? 'member',
                    'is_super_admin' => $user->isSuperAdmin(),
                    'is_admin' => $user->isAdmin(),
                    'avatar_path' => $user->avatar_path ?? null,
                    'theme' => $user->theme ?? 'light',
                ] : null,
                'workspaces' => $workspaces,
                'currentWorkspace' => $currentWorkspace,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'csrf_token' => fn () => csrf_token(),
            'notifications' => fn () => $user ? [
                'unread_count' => \App\Models\Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
                'list' => \App\Models\Notification::where('user_id', $user->id)->latest()->take(6)->get(),
            ] : null,
        ];
    }
}

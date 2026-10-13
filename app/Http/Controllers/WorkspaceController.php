<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $workspaces = $user->workspaces()
            ->withCount(['members', 'spaces'])
            ->with('owner:id,name,email')
            ->get()
            ->map(fn ($ws) => [
                'id' => $ws->id,
                'name' => $ws->name,
                'slug' => $ws->slug,
                'owner' => $ws->owner,
                'is_owner' => $ws->owner_id === $user->id,
                'role' => $ws->pivot->role ?? 'member',
                'members_count' => $ws->members_count,
                'spaces_count' => $ws->spaces_count,
                'is_active' => $ws->id === $request->session()->get('active_workspace_id'),
                'created_at' => $ws->created_at->format('d M Y'),
            ]);

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = Auth::user();

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('workspaces.index')
            ->with('success', "Workspace '{$workspace->name}' berhasil dibuat!");
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = Auth::user();

        // Verifikasi bahwa user adalah anggota workspace ini
        if (! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses ke workspace ini.');
        }

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('dashboard')
            ->with('success', "Beralih ke workspace '{$workspace->name}'.");
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user();

        return view('admin.profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['username'])) {
            $userData['username'] = strtolower($validated['username']);
        }

        if ($request->hasFile('avatar')) {
            $this->deleteOldAvatar($user->avatar_url);
            $userData['avatar_url'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteOldAvatar($user->avatar_url);
            $userData['avatar_url'] = null;
        }

        if (! empty($validated['new_password'])) {
            $userData['password'] = Hash::make($validated['new_password']);
        }

        $user->update($userData);

        AuditLog::log('update_profile', 'User', (string) $user->id, "Memperbarui profil akun sendiri ({$user->name})");

        return back()->with('success', 'Profil dan keamanan akun berhasil diperbarui!');
    }

    private function deleteOldAvatar(?string $oldAvatar): void
    {
        if (! $oldAvatar) {
            return;
        }

        $clean = preg_replace('#^/?storage/#', '', $oldAvatar);
        if (Storage::disk('public')->exists($clean)) {
            Storage::disk('public')->delete($clean);
        }
    }
}

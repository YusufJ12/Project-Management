@extends('admin.layouts.master')

@section('title', 'Operator Admin | NexSphere Master Hub')
@section('pagetitle', 'Admin Operator Master Hub')
@section('title-sub', 'Sistem')

@section('content')
<div class="row g-3">
    <!-- Form Tambah Operator -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-user-add-line me-2"></i>Tambah Operator Baru</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required>
                        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Password Awal <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold fs-13">Peran / Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="admin">Admin Operator Biasa</option>
                            <option value="super_admin">Super Admin (Akses Penuh Vault)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="ri-user-follow-line me-1"></i> Simpan Operator
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Operator List -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-team-line me-2"></i>Daftar Administrator Aktif</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-12">
                            <tr>
                                <th>Nama & Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13">
                            @foreach($users as $u)
                                @php
                                    $uInitials = collect(explode(' ', $u->name))
                                        ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                        ->take(2)
                                        ->implode('');
                                    $uAvatar = $u->avatar_url ? asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $u->avatar_url), '/')) : null;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($uAvatar)
                                                <img src="{{ $uAvatar }}" alt="{{ $u->name }}" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                                            @else
                                                <div class="bg-primary-subtle text-primary fw-bold fs-12 d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px;">
                                                    {{ $uInitials ?: 'OP' }}
                                                </div>
                                            @endif
                                            <div>
                                                <strong class="text-dark">{{ $u->name }}</strong>
                                                <small class="text-muted fs-11 d-block font-monospace">&#64;{{ $u->username }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $u->email }}</td>
                                    <td>
                                        @if($u->role === 'super_admin')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Super Admin</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">Admin</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($u->is_active)
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <!-- Edit Button & Modal Trigger -->
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $u->id }}" title="Edit Operator">
                                                <i class="ri-edit-line"></i>
                                            </button>

                                            @if($u->id !== auth()->id())
                                                <!-- Toggle Active/Inactive -->
                                                <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm {{ $u->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <i class="{{ $u->is_active ? 'ri-user-unfollow-line' : 'ri-user-shared-line' }}"></i>
                                                    </button>
                                                </form>

                                                <!-- Delete Operator -->
                                                <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus operator {{ $u->name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Operator">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="badge bg-light text-muted fs-11">Anda</span>
                                            @endif
                                        </div>

                                        <!-- Modal Edit Operator -->
                                        <div class="modal fade text-start" id="editUserModal{{ $u->id }}" tabindex="-1" aria-labelledby="editUserModalLabel{{ $u->id }}" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow">
                                                    <form method="POST" action="{{ route('admin.users.update', $u->id) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-header border-bottom">
                                                            <h6 class="modal-title fw-bold text-primary" id="editUserModalLabel{{ $u->id }}">
                                                                <i class="ri-user-settings-line me-1"></i> Edit Data Operator
                                                            </h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold fs-13">Nama Lengkap <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" class="form-control" value="{{ old('name', $u->name) }}" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold fs-13">Username <span class="text-danger">*</span></label>
                                                                <input type="text" name="username" class="form-control" value="{{ old('username', $u->username) }}" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold fs-13">Email <span class="text-danger">*</span></label>
                                                                <input type="email" name="email" class="form-control" value="{{ old('email', $u->email) }}" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold fs-13">Peran / Role <span class="text-danger">*</span></label>
                                                                <select name="role" class="form-select" required>
                                                                    <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin Operator Biasa</option>
                                                                    <option value="super_admin" {{ $u->role === 'super_admin' ? 'selected' : '' }}>Super Admin (Akses Penuh Vault)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label fw-semibold fs-13">Password Baru <span class="text-muted fw-normal fs-11">(Kosongkan jika tidak diubah)</span></label>
                                                                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-primary fw-semibold">
                                                                <i class="ri-save-line me-1"></i> Simpan Perubahan
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if($users->hasPages())
                <div class="card-footer bg-transparent border-top py-3">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

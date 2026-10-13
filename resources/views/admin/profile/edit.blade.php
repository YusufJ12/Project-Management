@extends('admin.layouts.master')

@section('title', 'Profil Saya | NexSphere Master Hub')
@section('title-sub', 'Akun')
@section('pagetitle', 'Kelola Profil & Keamanan Akun')

@section('content')
<form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" id="profileForm">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Kolom Kiri: Avatar Card --}}
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 text-center">
                <div class="card-body p-4">
                    {{-- Avatar Circle --}}
                    <div class="position-relative d-inline-block rounded-circle overflow-hidden border border-2 shadow-sm mb-3"
                        id="avatarWrap" style="width:110px;height:110px;background:#f1f5f9;cursor:pointer" title="Ganti foto profil">
                        @if($user->avatar_url)
                            <img id="avatarPreviewImg" src="{{ asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $user->avatar_url), '/')) }}" alt="avatar"
                                style="width:100%;height:100%;object-fit:cover;display:block">
                            <div id="avatarInitials" class="w-100 h-100 d-flex align-items-center justify-content-center fw-bold fs-2 text-primary bg-primary bg-opacity-10" style="display:none!important">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                        @else
                            <img id="avatarPreviewImg" src="" alt="avatar"
                                style="width:100%;height:100%;object-fit:cover;display:none">
                            <div id="avatarInitials" class="w-100 h-100 d-flex align-items-center justify-content-center fw-bold fs-2 text-primary bg-primary bg-opacity-10">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                        @endif

                        <label for="avatar" class="visually-hidden">Foto Profil</label>
                        <input type="file" name="avatar" id="avatar" accept="image/png,image/jpeg,image/webp"
                            style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;z-index:3">

                        <div class="avatar-hover-overlay position-absolute bottom-0 start-0 end-0 d-flex align-items-center justify-content-center pb-1 pt-2"
                            style="background:rgba(0,0,0,.6);z-index:2;pointer-events:none;transition:transform .2s ease, opacity .2s ease;">
                            <span class="btn btn-sm btn-light py-0 px-2 fw-medium" style="font-size:11px;line-height:1.4">
                                <i class="bi bi-camera me-1"></i>Ganti
                            </span>
                        </div>
                    </div>

                    @if($user->avatar_url)
                        <div class="mb-2">
                            <button type="submit" name="remove_avatar" value="1" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:11px" onclick="return confirm('Hapus foto profil?')">
                                <i class="bi bi-trash me-1"></i>Hapus Foto
                            </button>
                        </div>
                    @endif

                    <h5 class="fw-bold mb-1 text-dark">{{ $user->name }}</h5>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-capitalize fs-12 px-2 py-1">
                        {{ str_replace('_', ' ', $user->role) }}
                    </span>
                    <p class="text-muted fs-12 mt-2 mb-0">Operator sejak {{ $user->created_at->format('M Y') }}</p>
                    <small class="text-muted d-block mt-1">JPG, PNG, WEBP &bull; Maks 2MB</small>
                    @error('avatar') <div class="text-danger fs-12 mt-2">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Form Data & Password --}}
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 border-bottom">
                    <h6 class="card-title mb-0 fw-bold fs-15 text-primary"><i class="ri-user-settings-line me-2"></i>Informasi Akun Operator</h6>
                </div>

                <div class="card-body p-4">
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Periksa kesalahan berikut:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="name" class="form-label fw-semibold fs-13">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $user->name) }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="username" class="form-label fw-semibold fs-13">Username Login</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fs-13">@</span>
                                <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror"
                                    value="{{ old('username', $user->username) }}" placeholder="Username login">
                            </div>
                            @error('username') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="email" class="form-label fw-semibold fs-13">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email) }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12"><hr class="my-2"></div>

                        <div class="col-12">
                            <h6 class="fw-bold mb-1 text-dark"><i class="ri-lock-password-line me-1 text-warning"></i> Ganti Kata Sandi <span class="text-muted fw-normal fs-12">(Opsional)</span></h6>
                            <p class="fs-12 text-muted mb-3">Biarkan kosong jika tidak ingin mengubah kata sandi Anda.</p>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="current_password" class="form-label fw-semibold fs-13">Sandi Saat Ini</label>
                            <input type="password" name="current_password" id="current_password"
                                class="form-control @error('current_password') is-invalid @enderror" placeholder="••••••••">
                            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="new_password" class="form-label fw-semibold fs-13">Sandi Baru</label>
                            <input type="password" name="new_password" id="new_password"
                                class="form-control @error('new_password') is-invalid @enderror" placeholder="••••••••">
                            @error('new_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="new_password_confirmation" class="form-label fw-semibold fs-13">Konfirmasi Sandi Baru</label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation"
                                class="form-control" placeholder="••••••••">
                        </div>

                        <div class="col-12 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                <i class="ri-save-line me-1"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('styles')
<style>
#avatarWrap .avatar-hover-overlay {
    opacity: 0;
    transform: translateY(100%);
}
#avatarWrap:hover .avatar-hover-overlay {
    opacity: 1;
    transform: translateY(0);
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const input    = document.getElementById('avatar');
    const imgEl    = document.getElementById('avatarPreviewImg');
    const initials = document.getElementById('avatarInitials');
    if (!input) return;

    input.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        const url = URL.createObjectURL(this.files[0]);
        imgEl.src = url;
        imgEl.style.display = 'block';
        if (initials) initials.style.setProperty('display', 'none', 'important');
    });
})();
</script>
@endpush

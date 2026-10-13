@extends('admin.layouts.master')

@section('title', 'Edit Tenant | ' . $tenant->company_name)
@section('pagetitle', 'Edit Tenant')
@section('title-sub', $tenant->company_name)

@section('content')
<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-edit-line me-2"></i>Perbarui Data Tenant</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.tenants.update', $tenant->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13">Logo / Foto Profil Perusahaan</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="border rounded-3 p-1 bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 72px; height: 72px; min-width: 72px;">
                                    @if($tenant->logo_asset_url)
                                        <img id="logoPreview" src="{{ $tenant->logo_asset_url }}" alt="{{ $tenant->company_name }}" class="w-100 h-100 object-fit-contain">
                                        <i id="logoPlaceholder" class="ri-image-add-line fs-28 text-muted d-none"></i>
                                    @else
                                        <img id="logoPreview" src="" alt="Preview Logo" class="w-100 h-100 object-fit-contain d-none">
                                        <i id="logoPlaceholder" class="ri-image-add-line fs-28 text-muted"></i>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="logo" id="logoInput" class="form-control @error('logo') is-invalid @enderror" accept="image/png, image/jpeg, image/webp, image/svg+xml">
                                    <div class="form-text fs-12 text-muted">Format: PNG, JPG, WEBP, SVG (Maks. 2MB). Unggah file baru untuk memperbarui logo.</div>
                                    @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    @if($tenant->logo_url)
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogoCheck">
                                            <label class="form-check-label text-danger fs-12" for="removeLogoCheck">
                                                <i class="ri-delete-bin-line me-1"></i>Hapus logo saat ini (kembalikan ke inisial default)
                                            </label>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold fs-13">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror"
                                   value="{{ old('company_name', $tenant->company_name) }}" required>
                            @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Kode Perusahaan</label>
                            <input type="text" class="form-control" value="{{ $tenant->company_code }}" disabled>
                            <small class="text-muted fs-11">Kode unik permanen.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Nama PIC <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control"
                                   value="{{ old('pic_name', $tenant->pic_name) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Email PIC <span class="text-danger">*</span></label>
                            <input type="email" name="pic_email" class="form-control"
                                   value="{{ old('pic_email', $tenant->pic_email) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">WhatsApp PIC</label>
                            <input type="text" name="pic_whatsapp" class="form-control"
                                   value="{{ old('pic_whatsapp', $tenant->pic_whatsapp) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Domain Web Frontend</label>
                            <input type="text" name="domain_frontend" class="form-control"
                                   value="{{ old('domain_frontend', $tenant->domain_frontend) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Domain Backend API</label>
                            <input type="text" name="domain_backend" class="form-control"
                                   value="{{ old('domain_backend', $tenant->domain_backend) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Status Operasional <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ old('status', $tenant->status) === 'active' ? 'selected' : '' }}>Aktif (Active)</option>
                                <option value="trial" {{ old('status', $tenant->status) === 'trial' ? 'selected' : '' }}>Masa Uji Coba (Trial)</option>
                                <option value="suspended" {{ old('status', $tenant->status) === 'suspended' ? 'selected' : '' }}>Ditangguhkan (Suspended / Remote Kill)</option>
                                <option value="terminated" {{ old('status', $tenant->status) === 'terminated' ? 'selected' : '' }}>Dihentikan Permanen (Terminated)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Alasan Penangguhan (Jika status Suspended)</label>
                            <input type="text" name="suspension_reason" class="form-control"
                                   value="{{ old('suspension_reason', $tenant->suspension_reason) }}" placeholder="cth: Tagihan menunggak 14 hari">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13">Catatan Internal</label>
                            <textarea name="notes" class="form-control" rows="3">{{ old('notes', $tenant->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-light px-4">Kembali</a>
                        <button type="submit" class="btn btn-primary px-5 fw-semibold">
                            <i class="ri-check-line me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('logoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
            const preview = document.getElementById('logoPreview');
            const placeholder = document.getElementById('logoPlaceholder');
            preview.src = evt.target.result;
            preview.classList.remove('d-none');
            placeholder.classList.add('d-none');
            // Uncheck remove_logo if checked
            const removeCheck = document.getElementById('removeLogoCheck');
            if (removeCheck) removeCheck.checked = false;
        };
        reader.readAsDataURL(file);
    }
});
</script>
@endpush

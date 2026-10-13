@extends('admin.layouts.master')

@section('title', 'Tambah Tenant Klien Baru | NexSphere Master Hub')
@section('pagetitle', 'Tambah Klien Baru')
@section('title-sub', 'Tenants')

@section('content')
<div class="row g-3">
    <div class="col-12">
        <form method="POST" action="{{ route('admin.tenants.store') }}" enctype="multipart/form-data">
            @csrf

            <!-- Section 1: Profil Perusahaan & PIC -->
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-building-line me-2"></i>1. Identitas Perusahaan & PIC</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13">Logo / Foto Profil Perusahaan</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="border rounded-3 p-1 bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 72px; height: 72px; min-width: 72px;">
                                    <img id="logoPreview" src="" alt="Preview Logo" class="w-100 h-100 object-fit-contain d-none">
                                    <i id="logoPlaceholder" class="ri-image-add-line fs-28 text-muted"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="logo" id="logoInput" class="form-control @error('logo') is-invalid @enderror" accept="image/png, image/jpeg, image/webp, image/svg+xml">
                                    <div class="form-text fs-12 text-muted">Format: PNG, JPG, WEBP, SVG (Maks. 2MB). Logo ini otomatis disinkronkan ke VPS klien (NexSphere HRIS) untuk branding login & navbar.</div>
                                    @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold fs-13">Nama Perusahaan / Klien <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror"
                                   value="{{ old('company_name') }}" placeholder="Contoh: PT Sumber Makmur Abadi" required>
                            @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Kode Perusahaan (Company Code)</label>
                            <input type="text" name="company_code" class="form-control text-uppercase @error('company_code') is-invalid @enderror"
                                   value="{{ old('company_code') }}" placeholder="Otomatis jika kosong (cth: NEX-SMA)">
                            <small class="text-muted fs-11">Biarkan kosong untuk auto-generate kode.</small>
                            @error('company_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Nama PIC <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control @error('pic_name') is-invalid @enderror"
                                   value="{{ old('pic_name') }}" placeholder="Nama penanggung jawab" required>
                            @error('pic_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Email PIC <span class="text-danger">*</span></label>
                            <input type="email" name="pic_email" class="form-control @error('pic_email') is-invalid @enderror"
                                   value="{{ old('pic_email') }}" placeholder="pic@perusahaan.com" required>
                            @error('pic_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">WhatsApp Tagihan / Alert</label>
                            <input type="text" name="pic_whatsapp" class="form-control @error('pic_whatsapp') is-invalid @enderror"
                                   value="{{ old('pic_whatsapp') }}" placeholder="08123456789">
                            @error('pic_whatsapp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Domain Web Frontend</label>
                            <input type="text" name="domain_frontend" class="form-control"
                                   value="{{ old('domain_frontend') }}" placeholder="hris.perusahaan.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Domain Backend API</label>
                            <input type="text" name="domain_backend" class="form-control"
                                   value="{{ old('domain_backend') }}" placeholder="api-hris.perusahaan.com">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Paket Lisensi & Billing -->
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-shield-star-line me-2"></i>2. Paket Modul & Siklus Langganan</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Pilih Paket Lisensi <span class="text-danger">*</span></label>
                            <select name="package_id" class="form-select @error('package_id') is-invalid @enderror" required>
                                @foreach($packages as $pkg)
                                    <option value="{{ $pkg->id }}" {{ old('package_id') == $pkg->id ? 'selected' : '' }}>
                                        {{ $pkg->name }} — Maks {{ $pkg->max_employees }} Karyawan (Rp {{ number_format($pkg->monthly_price, 0, ',', '.') }}/bln)
                                    </option>
                                @endforeach
                            </select>
                            @error('package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Siklus Penagihan <span class="text-danger">*</span></label>
                            <select name="billing_cycle" class="form-select" required>
                                <option value="monthly" {{ old('billing_cycle') == 'monthly' ? 'selected' : '' }}>Bulanan (Monthly)</option>
                                <option value="yearly" {{ old('billing_cycle') == 'yearly' ? 'selected' : '' }}>Tahunan (Yearly - Hemat)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Informasi VPS & Infrastruktur Node -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-server-line me-2"></i>3. Detail Server VPS Node Klien</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Penyedia VPS (Hosting)</label>
                            <input type="text" name="vps_provider" class="form-control"
                                   value="{{ old('vps_provider', 'Domainesia') }}" placeholder="Domainesia / Hetzner / Contabo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">IP Address Server VPS</label>
                            <input type="text" name="vps_ip_address" class="form-control"
                                   value="{{ old('vps_ip_address') }}" placeholder="Contoh: 202.155.94.108">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13">Catatan Khusus Klien</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Catatan internal kontrak, SLA, atau konfigurasi khusus...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-5">
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-light px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-5 fw-semibold shadow-sm">
                    <i class="ri-save-line me-1"></i> Simpan & Generate Brankas Kredensial
                </button>
            </div>
        </form>
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
        };
        reader.readAsDataURL(file);
    }
});
</script>
@endpush

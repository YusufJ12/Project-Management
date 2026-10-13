@extends('admin.layouts.master')

@section('title', 'Buat Paket Baru | NexSphere Master Hub')
@section('pagetitle', 'Buat Paket Lisensi Baru')
@section('title-sub', 'Paket')

@section('content')
<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-add-box-line me-2"></i>Form Buat Paket Lisensi</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.packages.store') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold fs-13">Nama Paket <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Contoh: Enterprise Ultimate" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Maks. Karyawan <span class="text-danger">*</span></label>
                            <input type="number" name="max_employees" class="form-control"
                                   value="{{ old('max_employees', 100) }}" min="1" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13">Deskripsi Singkat</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Penjelasan fitur dan target konsumen...">{{ old('description') }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Harga Bulanan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="monthly_price" class="form-control"
                                   value="{{ old('monthly_price', 1000000) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Harga Tahunan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="yearly_price" class="form-control"
                                   value="{{ old('yearly_price', 10000000) }}" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13 d-block mb-2">Pilih Modul HRIS yang Diaktifkan <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                @foreach($availableModules as $modCode => $modName)
                                    <div class="col-md-6">
                                        <div class="form-check p-2 border rounded">
                                            <input class="form-check-input ms-1" type="checkbox" name="enabled_modules[]" value="{{ $modCode }}" id="mod-{{ $modCode }}"
                                                {{ is_array(old('enabled_modules')) && in_array($modCode, old('enabled_modules'), true) ? 'checked' : '' }}>
                                            <label class="form-check-label fs-13 fw-semibold ms-2" for="mod-{{ $modCode }}">
                                                {{ $modName }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                                <label class="form-check-label fs-13 fw-semibold" for="is_active">Paket Aktif & Dapat Dipilih Klien</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="{{ route('admin.packages.index') }}" class="btn btn-light px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-5 fw-semibold">
                            <i class="ri-save-line me-1"></i> Simpan Paket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

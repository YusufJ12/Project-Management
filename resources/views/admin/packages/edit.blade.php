@extends('admin.layouts.master')

@section('title', 'Edit Paket | ' . $package->name)
@section('pagetitle', 'Edit Paket: ' . $package->name)
@section('title-sub', 'Paket')

@section('content')
<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-edit-line me-2"></i>Edit Detail Paket & Modul</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.packages.update', $package->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold fs-13">Nama Paket <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $package->name) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-13">Maks. Karyawan <span class="text-danger">*</span></label>
                            <input type="number" name="max_employees" class="form-control"
                                   value="{{ old('max_employees', $package->max_employees) }}" min="1" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13">Deskripsi Singkat</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $package->description) }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Harga Bulanan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="monthly_price" class="form-control"
                                   value="{{ old('monthly_price', $package->monthly_price) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Harga Tahunan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="yearly_price" class="form-control"
                                   value="{{ old('yearly_price', $package->yearly_price) }}" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13 d-block mb-2">Pilih Modul HRIS yang Diaktifkan <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                @php
                                    $currentModules = old('enabled_modules', $package->enabled_modules ?? []);
                                @endphp
                                @foreach($availableModules as $modCode => $modName)
                                    <div class="col-md-6">
                                        <div class="form-check p-2 border rounded">
                                            <input class="form-check-input ms-1" type="checkbox" name="enabled_modules[]" value="{{ $modCode }}" id="mod-{{ $modCode }}"
                                                {{ in_array($modCode, $currentModules, true) ? 'checked' : '' }}>
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
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                    {{ old('is_active', $package->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fs-13 fw-semibold" for="is_active">Paket Aktif & Dapat Dipilih Klien</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="{{ route('admin.packages.index') }}" class="btn btn-light px-4">Kembali</a>
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

@extends('admin.layouts.master')

@section('title', 'Paket & Modul HRIS | NexSphere Master Hub')
@section('pagetitle', 'Paket & Modul Lisensi')
@section('title-sub', 'Paket')

@section('content')
<div class="row mb-3 align-items-center">
    <div class="col-md-6">
        <h6 class="text-muted fs-13 mb-0">Kelola paket langganan dan kontrol modul yang diberikan ke setiap instans VPS HRIS.</h6>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="{{ route('admin.packages.create') }}" class="btn btn-primary">
            <i class="ri-add-line me-1"></i> Buat Paket Baru
        </a>
    </div>
</div>

<div class="row g-3">
    @forelse($packages as $pkg)
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">{{ $pkg->name }}</h5>
                        <small class="text-muted fs-11 font-monospace">slug: {{ $pkg->slug }}</small>
                    </div>
                    <span class="badge {{ $pkg->is_active ? 'bg-success-subtle text-success' : 'bg-secondary' }}">
                        {{ $pkg->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted fs-13 mb-3">{{ $pkg->description ?: 'Tidak ada deskripsi.' }}</p>

                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="d-flex justify-content-between mb-1 fs-13">
                            <span class="text-muted">Batas Maks Karyawan:</span>
                            <strong class="text-dark">{{ $pkg->max_employees }} Karyawan</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1 fs-13">
                            <span class="text-muted">Harga Bulanan:</span>
                            <strong class="text-success">Rp {{ number_format($pkg->monthly_price, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between fs-13">
                            <span class="text-muted">Harga Tahunan:</span>
                            <strong class="text-primary">Rp {{ number_format($pkg->yearly_price, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fs-12 fw-semibold text-muted text-uppercase d-block mb-2">Modul yang Termasuk ({{ count($pkg->enabled_modules ?? []) }}):</label>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($pkg->enabled_modules ?? [] as $m)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                                    {{ $m }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top py-3 d-flex justify-content-between align-items-center">
                    <span class="fs-12 text-muted">{{ $pkg->subscriptions_count }} Tenant Menggunakan</span>
                    <a href="{{ route('admin.packages.edit', $pkg->id) }}" class="btn btn-sm btn-light-primary">
                        <i class="ri-edit-line me-1"></i> Edit Paket
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-light text-center py-4">Belum ada paket langganan.</div>
        </div>
    @endforelse
</div>
@endsection

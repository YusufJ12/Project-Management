@extends('admin.layouts.master')

@section('title', 'Direktori Klien (Tenants) | NexSphere Master Hub')
@section('pagetitle', 'Direktori Klien HRIS')
@section('title-sub', 'Tenants')

@section('content')
<div class="row mb-3 align-items-center">
    <div class="col-md-6 mb-2 mb-md-0">
        <form method="GET" action="{{ route('admin.tenants.index') }}" class="d-flex gap-2">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Cari nama PT, kode, atau PIC..." value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn-light">Cari</button>
            @if(request('search'))
                <a href="{{ route('admin.tenants.index', ['status' => request('status')]) }}" class="btn btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary">
            <i class="ri-add-line me-1"></i> Tambah Tenant Baru
        </a>
    </div>
</div>

<!-- Status Filters -->
<div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="{{ route('admin.tenants.index', ['search' => request('search')]) }}"
       class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-light' }}">
        Semua
    </a>
    <a href="{{ route('admin.tenants.index', ['status' => 'active', 'search' => request('search')]) }}"
       class="btn btn-sm {{ request('status') === 'active' ? 'btn-success' : 'btn-light' }}">
        Aktif
    </a>
    <a href="{{ route('admin.tenants.index', ['status' => 'trial', 'search' => request('search')]) }}"
       class="btn btn-sm {{ request('status') === 'trial' ? 'btn-warning' : 'btn-light' }}">
        Trial
    </a>
    <a href="{{ route('admin.tenants.index', ['status' => 'suspended', 'search' => request('search')]) }}"
       class="btn btn-sm {{ request('status') === 'suspended' ? 'btn-danger' : 'btn-light' }}">
        Ditangguhkan (Suspended)
    </a>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-12">
                    <tr>
                        <th>Perusahaan / Tenant</th>
                        <th>PIC Kontak</th>
                        <th>Domain / Host</th>
                        <th>Paket & Lisensi</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse($tenants as $tenant)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($tenant->logo_asset_url)
                                        <img src="{{ $tenant->logo_asset_url }}" alt="{{ $tenant->company_name }}" class="rounded-3 border me-3 object-fit-contain p-1 bg-white" style="width: 38px; height: 38px; min-width: 38px;">
                                    @else
                                        <div class="avatar-sm bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center me-3 fw-bold fs-14" style="width: 38px; height: 38px; min-width: 38px;">
                                            {{ substr($tenant->company_code, 0, 3) }}
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="fw-bold text-dark text-decoration-none d-block">
                                            {{ $tenant->company_name }}
                                        </a>
                                        <span class="badge bg-light text-secondary fs-11">{{ $tenant->company_code }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><strong class="text-dark">{{ $tenant->pic_name }}</strong></div>
                                <div class="text-muted fs-12">{{ $tenant->pic_email }}</div>
                                @if($tenant->pic_whatsapp)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $tenant->pic_whatsapp) }}" target="_blank" class="text-success fs-11 text-decoration-none">
                                        <i class="ri-whatsapp-line"></i> {{ $tenant->pic_whatsapp }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if($tenant->domain_frontend)
                                    <div><a href="https://{{ $tenant->domain_frontend }}" target="_blank" class="text-decoration-none text-primary fs-12"><i class="ri-external-link-line me-1"></i>{{ $tenant->domain_frontend }}</a></div>
                                @else
                                    <span class="text-muted fs-12">-</span>
                                @endif
                                @if($tenant->vault?->vps_ip_address)
                                    <small class="badge bg-light text-muted border fs-10 mt-1">IP: {{ $tenant->vault->vps_ip_address }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold text-primary">{{ $tenant->activeSubscription?->package?->name ?? 'Custom' }}</span>
                                <div class="text-muted fs-11">
                                    Exp: {{ $tenant->activeSubscription?->end_date ? $tenant->activeSubscription->end_date->format('d M Y') : 'N/A' }}
                                </div>
                            </td>
                            <td>
                                @if($tenant->status === 'active')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Aktif</span>
                                @elseif($tenant->status === 'suspended')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Ditangguhkan</span>
                                @elseif($tenant->status === 'trial')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Trial</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">{{ ucfirst($tenant->status) }}</span>
                                @endif

                                @php
                                    $latestTele = $tenant->latestTelemetry;
                                    $healthStatus = $tenant->node_health_status;
                                @endphp
                                @if($healthStatus === 'healthy')
                                    <div class="mt-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-10" title="Node VPS terhubung (RAM: {{ $latestTele->ram_usage_percent ?? 0 }}%, CPU: {{ $latestTele->cpu_usage_percent ?? 0 }}%)">
                                            <span class="spinner-grow spinner-grow-sm me-1 text-success" style="width: 0.35rem; height: 0.35rem;" role="status"></span>Node Online
                                        </span>
                                    </div>
                                @elseif($healthStatus === 'warning')
                                    <div class="mt-1">
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-10" title="Peringatan Node: RAM {{ $latestTele->ram_usage_percent ?? 0 }}%, CPU {{ $latestTele->cpu_usage_percent ?? 0 }}%">
                                            <i class="ri-alert-line me-1"></i>Node Warning
                                        </span>
                                    </div>
                                @elseif($healthStatus === 'offline' && $latestTele)
                                    <div class="mt-1">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10" title="Terakhir lapor: {{ $latestTele->reported_at->diffForHumans() }}">
                                            <i class="ri-time-line me-1"></i>Node Offline
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-sm btn-light-primary" title="Buka Control Plane & Vault">
                                        <i class="ri-eye-line me-1"></i> Kelola
                                    </a>
                                    <form method="POST" action="{{ route('admin.tenants.destroy', $tenant->id) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tenant {{ $tenant->company_name }} ({{ $tenant->company_code }}) beserta seluruh brankas dan telemetrinya? Tindakan ini tidak dapat dibatalkan!')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Tenant">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-32 d-block mb-1"></i>
                                Tidak ada data tenant ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tenants->hasPages())
        <div class="card-footer bg-transparent border-top py-3">
            {{ $tenants->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

@extends('admin.layouts.master')

@section('title', 'Pusat Pemantauan Telemetri Seluruh Node VPS | NexSphere Master Hub')
@section('pagetitle', 'Pusat Kendali Telemetri Node VPS')
@section('title-sub', 'Multi-Node Cluster')

@section('content')
<!-- TOP STATS SUMMARY (MULTI-NODE CLUSTER 18+ VPS) -->
<div class="row g-3 mb-4">
    <!-- Total Nodes -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-semibold text-uppercase">Total Node VPS Terhubung</span>
                        <h3 class="fw-bold mb-0 mt-1 text-dark">{{ $totalNodes }} <span class="fs-14 fw-normal text-muted">Server</span></h3>
                    </div>
                    <div class="avatar-md bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                        <i class="ri-server-line fs-22"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span>1 Server per Klien HRIS</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Online Nodes -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-semibold text-uppercase">Node Aktif (Online)</span>
                        <h3 class="fw-bold mb-0 mt-1 text-success">{{ $onlineNodes }} <span class="fs-14 fw-normal text-muted">Server</span></h3>
                    </div>
                    <div class="avatar-md bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                        <i class="ri-pulse-fill fs-22"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span class="text-success"><i class="ri-checkbox-circle-fill me-1"></i>Merespons dalam 5 menit terakhir</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Average RAM Usage -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-semibold text-uppercase">Rata-rata Penggunaan RAM</span>
                        <h3 class="fw-bold mb-0 mt-1 text-info">{{ $avgRam }}<span class="fs-14 fw-normal text-muted">%</span></h3>
                    </div>
                    <div class="avatar-md bg-info-subtle text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                        <i class="ri-pie-chart-2-line fs-22"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span>Beban memori cluster server</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Average CPU Usage -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-semibold text-uppercase">Rata-rata Beban CPU</span>
                        <h3 class="fw-bold mb-0 mt-1 text-primary">{{ $avgCpu }}<span class="fs-14 fw-normal text-muted">%</span></h3>
                    </div>
                    <div class="avatar-md bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                        <i class="ri-cpu-line fs-22"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span>Beban komputasi cluster server</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MULTI-NODE TABLE -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-transparent border-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <h6 class="mb-0 fw-bold fs-15 text-primary">
                <i class="ri-server-fill me-2"></i>Status Realtime Seluruh Node VPS ({{ $totalNodes }} Server)
            </h6>
            <small class="text-muted fs-12">Setiap baris merepresentasikan 1 VPS fisik klien. Data diperbarui secara berkala dan otomatis.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-secondary border font-monospace fs-11">
                <i class="ri-time-line me-1"></i>Dicek: {{ now()->format('H:i:s') }}
            </span>
            <button type="button" class="btn btn-sm btn-outline-primary px-3" onclick="window.location.reload();">
                <i class="ri-refresh-line me-1"></i> Segarkan Halaman
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-12">
                    <tr>
                        <th>Status</th>
                        <th>Tenant / Perusahaan</th>
                        <th>Host / IP VPS</th>
                        <th>Versi</th>
                        <th>Total Karyawan</th>
                        <th style="min-width: 140px;">RAM Host</th>
                        <th style="min-width: 140px;">CPU Host</th>
                        <th>Database</th>
                        <th>Uptime</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse($telemetries as $tele)
                        @php
                            $isOnline = $tele->reported_at && $tele->reported_at->diffInMinutes(now()) <= 5;
                        @endphp
                        <tr>
                            <td>
                                @if($isOnline)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-11">
                                        <span class="spinner-grow spinner-grow-sm me-1 text-success" style="width: 0.4rem; height: 0.4rem;" role="status"></span>Online
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 fs-11">
                                        Offline
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($tele->tenant?->logo_asset_url)
                                        <img src="{{ $tele->tenant->logo_asset_url }}" alt="{{ $tele->tenant->company_name }}" class="rounded-circle border me-2 object-fit-contain p-1 bg-white" style="width: 32px; height: 32px; min-width: 32px;">
                                    @else
                                        <div class="avatar-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 32px; height: 32px; min-width: 32px;">
                                            {{ substr($tele->tenant?->company_code ?? 'NEX', 0, 3) }}
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.tenants.show', $tele->tenant_id) }}" class="fw-bold text-dark text-decoration-none d-block">
                                            {{ $tele->tenant?->company_name ?? 'N/A' }}
                                        </a>
                                        <span class="badge bg-light text-secondary fs-11">{{ $tele->tenant?->company_code }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="font-monospace fw-semibold text-dark fs-12">{{ $tele->ip_address ?: ($tele->tenant?->vault?->vps_ip_address ?: '-') }}</span>
                                <small class="text-muted fs-11 d-block">{{ $tele->tenant?->vault?->vps_provider ?? 'VPS' }}</small>
                            </td>
                            <td><span class="badge bg-dark text-light font-monospace">v{{ $tele->app_version }}</span></td>
                            <td><strong>{{ $tele->total_employees }}</strong> Orang</td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fw-bold {{ ($tele->ram_usage_percent ?? 0) > 85 ? 'text-danger' : 'text-info' }} fs-12">
                                        {{ $tele->ram_usage_percent ?? 0 }}%
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ ($tele->ram_usage_percent ?? 0) > 85 ? 'bg-danger' : 'bg-info' }}" role="progressbar" style="width: {{ min(100, $tele->ram_usage_percent ?? 0) }}%;"></div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fw-bold {{ ($tele->cpu_usage_percent ?? 0) > 85 ? 'bg-danger' : 'bg-primary' }} fs-12">
                                        {{ $tele->cpu_usage_percent ?? 0 }}%
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ ($tele->cpu_usage_percent ?? 0) > 85 ? 'bg-danger' : 'bg-primary' }}" role="progressbar" style="width: {{ min(100, $tele->cpu_usage_percent ?? 0) }}%;"></div>
                                </div>
                            </td>
                            <td>
                                @if($tele->db_healthy)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ri-check-line me-1"></i>Healthy</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="ri-close-line me-1"></i>Error</span>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace fs-12 text-dark">{{ gmdate("H:i:s", $tele->uptime_seconds) }}</span>
                                <small class="text-muted fs-10 d-block">{{ $tele->reported_at->diffForHumans() }}</small>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.tenants.show', $tele->tenant_id) }}" class="btn btn-sm btn-light border px-2 py-1 text-primary" title="Buka Detail Server VPS">
                                    <i class="ri-dashboard-line me-1"></i>Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="ri-server-line fs-36 text-muted d-block mb-2"></i>
                                Belum ada node VPS klien yang terhubung ke Master Hub.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($telemetries->hasPages())
        <div class="card-footer bg-transparent border-top py-3">
            {{ $telemetries->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

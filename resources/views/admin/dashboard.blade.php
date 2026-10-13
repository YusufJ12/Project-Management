@extends('admin.layouts.master')

@section('title', 'Dashboard Central Control Plane | NexSphere')
@section('pagetitle', 'Dashboard SaaS Control Plane')
@section('title-sub', 'Ringkasan')

@section('content')
<div class="row g-3 mb-4">
    <!-- Total Tenants -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 fw-semibold text-uppercase">Total Klien / Tenant</span>
                        <h3 class="fw-bold mb-0 mt-1 text-dark">{{ $totalTenants }}</h3>
                    </div>
                    <div class="avatar-md bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="ri-building-4-line fs-24"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>{{ $activeTenants }} Aktif</span> | 
                    <span class="text-warning fw-semibold">{{ $trialTenants }} Trial</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Active MRR -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 fw-semibold text-uppercase">Estimasi MRR</span>
                        <h4 class="fw-bold mb-0 mt-1 text-success">Rp {{ number_format($mrr, 0, ',', '.') }}</h4>
                    </div>
                    <div class="avatar-md bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="ri-money-dollar-circle-line fs-24"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span>Monthly Recurring Revenue</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Suspended Tenants -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 fw-semibold text-uppercase">Ditangguhkan (Suspended)</span>
                        <h3 class="fw-bold mb-0 mt-1 {{ $suspendedTenants > 0 ? 'text-danger' : 'text-muted' }}">{{ $suspendedTenants }}</h3>
                    </div>
                    <div class="avatar-md bg-danger-subtle text-danger rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="ri-alarm-warning-line fs-24"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span>Remote Kill-Switch aktif</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Unpaid Invoices -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 fw-semibold text-uppercase">Tagihan Tertunda</span>
                        <h4 class="fw-bold mb-0 mt-1 text-warning">{{ $unpaidInvoicesCount }} Invoice</h4>
                    </div>
                    <div class="avatar-md bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="ri-file-warning-line fs-24"></i>
                    </div>
                </div>
                <div class="mt-2 fs-12 text-muted">
                    <span>Total: Rp {{ number_format($unpaidInvoicesSum, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Latest Tenants Table -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold fs-15"><i class="ri-community-line me-2 text-primary"></i>Klien HRIS Terbaru</h6>
                <a href="{{ route('admin.tenants.create') }}" class="btn btn-sm btn-primary">
                    <i class="ri-add-line me-1"></i> Tambah Klien Baru
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-12">
                            <tr>
                                <th>Perusahaan</th>
                                <th>Paket & Kuota</th>
                                <th>Status Node</th>
                                <th>Status Layanan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13">
                            @forelse($latestTenants as $t)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($t->logo_asset_url)
                                                <img src="{{ $t->logo_asset_url }}" alt="{{ $t->company_name }}" class="rounded-circle border me-2 object-fit-contain p-1 bg-white" style="width: 34px; height: 34px; min-width: 34px;">
                                            @else
                                                <div class="avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 34px; height: 34px; min-width: 34px;">
                                                    {{ substr($t->company_code, 0, 3) }}
                                                </div>
                                            @endif
                                            <div>
                                                <a href="{{ route('admin.tenants.show', $t->id) }}" class="fw-semibold text-dark text-decoration-none d-block">
                                                    {{ $t->company_name }}
                                                </a>
                                                <span class="badge bg-light text-secondary fs-11">{{ $t->company_code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-primary d-block">{{ $t->activeSubscription?->package?->name ?? 'Tanpa Paket' }}</span>
                                        <small class="text-muted fs-11">Maks: {{ $t->activeSubscription?->getEffectiveMaxEmployees() ?? 0 }} Pegawai</small>
                                    </td>
                                    <td>
                                        @php
                                            $latestT = $t->latestTelemetry;
                                            $health = $t->node_health_status;
                                        @endphp
                                        @if($health === 'healthy')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-11">
                                                <i class="ri-pulse-fill me-1"></i>Online (v{{ $latestT->app_version }})
                                            </span>
                                            <div class="fs-11 text-muted mt-1">RAM: {{ $latestT->ram_usage_percent ?? 0 }}% | CPU: {{ $latestT->cpu_usage_percent ?? 0 }}%</div>
                                        @elseif($health === 'warning')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-11">
                                                <i class="ri-alert-line me-1"></i>Warning (v{{ $latestT->app_version }})
                                            </span>
                                            <div class="fs-11 text-muted mt-1">RAM: {{ $latestT->ram_usage_percent ?? 0 }}% | CPU: {{ $latestT->cpu_usage_percent ?? 0 }}%</div>
                                        @elseif($health === 'offline' && $latestT)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-11">
                                                <i class="ri-time-line me-1"></i>Offline
                                            </span>
                                            <div class="fs-11 text-muted mt-1">{{ $latestT->reported_at->diffForHumans() }}</div>
                                        @else
                                            <span class="badge bg-light text-muted border fs-11">Belum Terhubung</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($t->status === 'active')
                                            <span class="badge bg-success text-white">Active</span>
                                        @elseif($t->status === 'suspended')
                                            <span class="badge bg-danger text-white">Suspended</span>
                                        @elseif($t->status === 'trial')
                                            <span class="badge bg-warning text-dark">Trial</span>
                                        @else
                                            <span class="badge bg-secondary text-white">{{ ucfirst($t->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.tenants.show', $t->id) }}" class="btn btn-sm btn-light-primary icon-btn" title="Detail">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada data tenant klien.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top py-2 text-center">
                <a href="{{ route('admin.tenants.index') }}" class="fs-12 text-primary fw-semibold text-decoration-none">
                    Lihat Semua Tenant <i class="ri-arrow-right-line ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Node Telemetry & Audit Logs -->
    <div class="col-xl-4">
        <!-- Live Node Telemetry Feed -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15"><i class="ri-pulse-line me-2 text-success"></i>Telemetri Node VPS Terakhir</h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-unstyled mb-0">
                    @forelse($recentTelemetries as $tele)
                        <li class="pb-2 mb-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold fs-13">{{ $tele->tenant?->company_name ?? 'Node' }}</span>
                                <span class="badge bg-light text-muted fs-11">{{ $tele->reported_at->diffForHumans() }}</span>
                            </div>
                            <div class="d-flex justify-content-between fs-12 text-muted">
                                <span>Karyawan: <strong>{{ $tele->total_employees }}</strong></span>
                                <span>RAM: <strong>{{ $tele->ram_usage_percent ?? 0 }}%</strong></span>
                                <span>CPU: <strong>{{ $tele->cpu_usage_percent ?? 0 }}%</strong></span>
                            </div>
                        </li>
                    @empty
                        <li class="text-muted text-center py-2 fs-12">Belum ada data heartbeat diterima.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Audit Log Activity -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15"><i class="ri-shield-user-line me-2 text-danger"></i>Audit Brankas (UU PDP)</h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-unstyled mb-0">
                    @forelse($recentAuditLogs as $log)
                        <li class="pb-2 mb-2 border-bottom fs-12">
                            <div class="d-flex justify-content-between">
                                <strong class="text-dark">{{ $log->user?->name ?? 'Sistem' }}</strong>
                                <span class="text-muted fs-10">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="text-muted mt-1">{{ $log->description ?: $log->action }}</div>
                        </li>
                    @empty
                        <li class="text-muted text-center py-2 fs-12">Belum ada riwayat audit akses kredensial.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

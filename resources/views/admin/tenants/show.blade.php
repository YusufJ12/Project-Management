@extends('admin.layouts.master')

@section('title', 'Detail Tenant | ' . $tenant->company_name)
@section('pagetitle', $tenant->company_name)
@section('title-sub', 'Detail Tenant & Brankas')

@section('content')
<!-- Header Overview Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                @if($tenant->logo_asset_url)
                    <div class="rounded-3 border p-1 bg-white shadow-sm d-flex align-items-center justify-content-center overflow-hidden" style="width: 58px; height: 58px; min-width: 58px;">
                        <img src="{{ $tenant->logo_asset_url }}" alt="{{ $tenant->company_name }}" class="w-100 h-100 object-fit-contain">
                    </div>
                @else
                    <div class="avatar-lg bg-primary text-white rounded-3 d-flex align-items-center justify-content-center fw-bold fs-22 shadow-sm" style="width: 58px; height: 58px; min-width: 58px;">
                        {{ substr($tenant->company_code, 0, 3) }}
                    </div>
                @endif
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-dark">{{ $tenant->company_name }}</h4>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12 px-2 py-1">{{ $tenant->company_code }}</span>
                        @if($tenant->status === 'active')
                            <span class="badge bg-success text-white fs-12 px-2 py-1"><i class="ri-checkbox-circle-line me-1"></i>Aktif</span>
                        @elseif($tenant->status === 'suspended')
                            <span class="badge bg-danger text-white fs-12 px-2 py-1"><i class="ri-forbid-line me-1"></i>Ditangguhkan (Suspended)</span>
                        @elseif($tenant->status === 'trial')
                            <span class="badge bg-warning text-dark fs-12 px-2 py-1"><i class="ri-time-line me-1"></i>Trial</span>
                        @else
                            <span class="badge bg-secondary text-white fs-12 px-2 py-1">{{ ucfirst($tenant->status) }}</span>
                        @endif
                    </div>
                    <div class="text-muted fs-13 d-flex flex-wrap gap-3">
                        <span><i class="ri-user-line me-1 text-primary"></i>PIC: <strong>{{ $tenant->pic_name }}</strong> ({{ $tenant->pic_email }})</span>
                        @if($tenant->pic_whatsapp)
                            <span><i class="ri-whatsapp-line me-1 text-success"></i>{{ $tenant->pic_whatsapp }}</span>
                        @endif
                        @if($tenant->domain_frontend)
                            <span><i class="ri-global-line me-1 text-info"></i><a href="https://{{ $tenant->domain_frontend }}" target="_blank" class="text-decoration-none">{{ $tenant->domain_frontend }}</a></span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('admin.tenants.edit', $tenant->id) }}" class="btn btn-outline-secondary">
                    <i class="ri-edit-line me-1"></i> Edit Profil
                </a>

                <!-- Remote Kill-Switch Form -->
                <form method="POST" action="{{ route('admin.tenants.toggle-suspension', $tenant->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status operasional tenant ini?')">
                    @csrf
                    @if($tenant->status === 'suspended')
                        <button type="submit" class="btn btn-success">
                            <i class="ri-play-circle-line me-1"></i> Buka Pembekuan (Aktifkan)
                        </button>
                    @else
                        <button type="submit" class="btn btn-danger">
                            <i class="ri-shut-down-line me-1"></i> Remote Kill-Switch (Suspend)
                        </button>
                    @endif
                </form>

                <!-- Delete Tenant Form -->
                <form method="POST" action="{{ route('admin.tenants.destroy', $tenant->id) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS PERMANEN tenant {{ $tenant->company_name }} beserta seluruh data brankas, lisensi, dan telemetrinya? Tindakan ini tidak dapat dikembalikan!')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger" title="Hapus Tenant Permanen">
                        <i class="ri-delete-bin-line me-1"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">
            <i class="ri-dashboard-line me-1"></i> Ringkasan & Lisensi
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-vault" type="button">
            <i class="ri-shield-keyhole-line me-1 text-warning"></i> Brankas &amp; Password Manager ({{ $tenant->credentials->count() }})
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-env" type="button">
            <i class="ri-file-code-line me-1 text-success"></i> 1-Klik Generate .env
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-nginx" type="button">
            <i class="ri-global-line me-1 text-info"></i> Nginx &amp; SSL (HTTPS)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold d-flex align-items-center gap-1" data-bs-toggle="tab" data-bs-target="#tab-telemetry" type="button" id="tab-telemetry-nav">
            <i class="ri-heart-pulse-line me-1 text-danger"></i> Telemetri VPS Node
            @php
                $latestTele = $tenant->latestTelemetry;
                $nodeHealth = $tenant->node_health_status;
                $isNodeOnline = $nodeHealth === 'healthy';
            @endphp
            <span class="badge {{ $nodeHealth === 'healthy' ? 'bg-success-subtle text-success border border-success-subtle' : ($nodeHealth === 'warning' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : ($latestTele ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-muted border')) }} ms-1" id="tab-telemetry-badge">
                @if($nodeHealth === 'healthy')
                    <span class="spinner-grow spinner-grow-sm me-1 text-success" style="width: 0.45rem; height: 0.45rem;" role="status"></span>Online
                @elseif($nodeHealth === 'warning')
                    <i class="ri-alert-line me-1 text-warning"></i>Warning
                @elseif($latestTele)
                    Offline
                @else
                    Standby
                @endif
            </span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button">
            <i class="ri-history-line me-1"></i> Audit Trail (UU PDP)
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content">
    <!-- TAB 1: OVERVIEW & LISENSI -->
    <div class="tab-pane fade show active" id="tab-overview">
        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-medal-line me-2"></i>Status Langganan & Batas Karyawan</h6>
                    </div>
                    <div class="card-body p-4">
                        @if($tenant->activeSubscription)
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="text-muted fs-12 text-uppercase">Paket Aktif</span>
                                    <h4 class="fw-bold mb-0 text-dark">{{ $tenant->activeSubscription->package?->name ?? 'Custom Package' }}</h4>
                                </div>
                                <span class="badge bg-success-subtle text-success fs-12 px-3 py-2 border border-success-subtle">
                                    {{ strtoupper($tenant->activeSubscription->billing_cycle) }}
                                </span>
                            </div>

                            <ul class="list-group list-group-flush fs-13 mb-0">
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Masa Berlaku Lisensi</span>
                                    <strong class="text-dark">{{ $tenant->activeSubscription->start_date->format('d M Y') }} s/d {{ $tenant->activeSubscription->end_date->format('d M Y') }}</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Maksimal Kuota Karyawan</span>
                                    <strong class="text-primary fs-14">{{ $tenant->activeSubscription->getEffectiveMaxEmployees() }} Karyawan</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Status Siklus</span>
                                    <span class="badge bg-light text-dark">{{ ucfirst($tenant->activeSubscription->status) }}</span>
                                </li>
                            </ul>
                        @else
                            <div class="alert alert-warning mb-0 fs-13">Belum ada langganan aktif untuk tenant ini.</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Modul Aktif -->
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-checkbox-multiple-line me-2"></i>Modul HRIS yang Aktif untuk Klien Ini</h6>
                    </div>
                    <div class="card-body p-4">
                        @php
                            $activeModules = $tenant->activeSubscription ? $tenant->activeSubscription->getEffectiveModules() : [];
                            $allModules = [
                                'CORE_HR' => 'Core HR (Karyawan & Struktur)',
                                'ATTENDANCE' => 'Absensi & Jam Kerja',
                                'PAYROLL' => 'Penggajian Otomatis (Payroll)',
                                'LEAVES' => 'Manajemen Cuti & Izin',
                                'LOANS' => 'Pinjaman / Kasbon',
                                'DISCIPLINARY' => 'Surat Peringatan (Kedisiplinan)',
                                'OFFBOARDING' => 'Offboarding & Resign',
                                'RECRUITMENT' => 'Rekrutmen Pelamar (ATS)',
                                'PERFORMANCE_KPI' => 'Penilaian Kinerja (KPI)',
                                'ASSETS' => 'Inventaris & Aset',
                            ];
                        @endphp

                        <div class="row g-2">
                            @foreach($allModules as $modKey => $modLabel)
                                @php $isModActive = in_array($modKey, $activeModules, true); @endphp
                                <div class="col-sm-6">
                                    <div class="p-2 rounded border d-flex align-items-center gap-2 {{ $isModActive ? 'bg-primary-subtle border-primary-subtle text-primary' : 'bg-light text-muted opacity-50' }}">
                                        <i class="{{ $isModActive ? 'ri-checkbox-circle-fill text-primary' : 'ri-close-circle-line text-secondary' }} fs-18"></i>
                                        <span class="fs-12 fw-semibold">{{ $modLabel }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: TENANT CREDENTIAL VAULT (AES-256) -->
    <div class="tab-pane fade" id="tab-vault">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold fs-15 text-warning"><i class="ri-shield-keyhole-fill me-2"></i>Brankas Kredensial Server Terenkripsi AES-256</h6>
                    <small class="text-muted fs-12">Tersimpan aman di database dengan At-Rest Encryption. Sesuai UU Perlindungan Data Pribadi.</small>
                </div>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1">
                    <i class="ri-lock-fill me-1"></i> AES-256 Enabled
                </span>
            </div>
            <div class="card-body p-4">
                @if($tenant->vault)
                    <div class="alert alert-info py-2 px-3 fs-13 d-flex align-items-center gap-2 mb-4">
                        <i class="ri-information-line fs-18"></i>
                        <span>Tombol <strong>"Intip"</strong> akan membuka kunci enkripsi seketika dan mencatat identitas akun Anda di <strong>Audit Log</strong> demi kepatuhan keamanan.</span>
                    </div>

                    <div class="row g-3">
                        <!-- VPS SSH Details -->
                        <div class="col-md-6">
                            <div class="card border bg-light-subtle rounded-3 p-3">
                                <h6 class="fw-bold fs-13 mb-3 text-dark"><i class="ri-terminal-box-line me-1 text-primary"></i>Akses SSH Server VPS</h6>
                                <div class="mb-2">
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">IP Address & Port</label>
                                    <span class="fs-13 fw-semibold font-monospace">{{ $tenant->vault->vps_ip_address ?? '202.155.94.108' }}:{{ $tenant->vault->ssh_port ?? 22 }}</span>
                                </div>
                                <div class="mb-2">
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">Username SSH</label>
                                    <span class="fs-13 fw-semibold font-monospace">{{ $tenant->vault->ssh_username ?? 'root' }}</span>
                                </div>
                                <div>
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">Password SSH</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" class="form-control font-monospace secret-field" id="val-ssh_password" value="••••••••••••" readonly>
                                        <button class="btn btn-outline-secondary btn-reveal" type="button" data-field="ssh_password">
                                            <i class="ri-eye-line"></i> Intip
                                        </button>
                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-target="val-ssh_password">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Database Details -->
                        <div class="col-md-6">
                            <div class="card border bg-light-subtle rounded-3 p-3">
                                <h6 class="fw-bold fs-13 mb-3 text-dark"><i class="ri-database-2-line me-1 text-info"></i>Database PostgreSQL Klien</h6>
                                <div class="mb-2">
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">Nama Database & User</label>
                                    <span class="fs-13 fw-semibold font-monospace">{{ $tenant->vault->db_name }} / {{ $tenant->vault->db_user }}</span>
                                </div>
                                <div>
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">Password Database</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" class="form-control font-monospace secret-field" id="val-db_password" value="••••••••••••" readonly>
                                        <button class="btn btn-outline-secondary btn-reveal" type="button" data-field="db_password">
                                            <i class="ri-eye-line"></i> Intip
                                        </button>
                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-target="val-db_password">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Master Hub Secret & Redis -->
                        <div class="col-md-6">
                            <div class="card border bg-light-subtle rounded-3 p-3">
                                <h6 class="fw-bold fs-13 mb-3 text-dark"><i class="ri-key-2-line me-1 text-danger"></i>Shared Secret MASTER_HUB_SECRET</h6>
                                <p class="text-muted fs-12 mb-2">Token otentikasi unik untuk sinkronisasi telemetri & kill-switch antara VPS Node dengan Master Hub.</p>
                                <div>
                                    <div class="input-group input-group-sm">
                                        <input type="password" class="form-control font-monospace secret-field" id="val-master_hub_secret" value="••••••••••••" readonly>
                                        <button class="btn btn-outline-secondary btn-reveal" type="button" data-field="master_hub_secret">
                                            <i class="ri-eye-line"></i> Intip
                                        </button>
                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-target="val-master_hub_secret">
                                            <i class="ri-file-copy-line"></i> Salin
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Web SuperAdmin Default Login -->
                        <div class="col-md-6">
                            <div class="card border bg-light-subtle rounded-3 p-3">
                                <h6 class="fw-bold fs-13 mb-3 text-dark"><i class="ri-admin-line me-1 text-success"></i>Kredensial Login SuperAdmin Web</h6>
                                <div class="mb-2">
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">Username Web HRIS</label>
                                    <span class="fs-13 fw-semibold font-monospace">{{ $tenant->vault->superadmin_username ?? 'superadmin' }}</span>
                                </div>
                                <div>
                                    <label class="text-muted fs-11 text-uppercase fw-semibold mb-1 d-block">Password Web SuperAdmin</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" class="form-control font-monospace secret-field" id="val-superadmin_password" value="••••••••••••" readonly>
                                        <button class="btn btn-outline-secondary btn-reveal" type="button" data-field="superadmin_password">
                                            <i class="ri-eye-line"></i> Intip
                                        </button>
                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-target="val-superadmin_password">
                                            <i class="ri-file-copy-line"></i> Salin
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning mb-0 fs-13">Brankas kredensial belum dikonfigurasi.</div>
                @endif

                <hr class="my-4">

                <!-- Dynamic Password Manager Section -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold fs-15 text-dark mb-1">
                            <i class="ri-key-2-line me-1 text-primary"></i> Password Manager &amp; Kredensial Tambahan
                        </h6>
                        <small class="text-muted fs-12">Catat password root VPS, cPanel, Cloudflare, SMTP, atau akun penting klien lainnya secara aman (Terenkripsi AES-256).</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary px-3 shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#modalAddCredential">
                        <i class="ri-add-line me-1"></i> Tambah Kredensial Baru
                    </button>
                </div>

                @if($tenant->credentials->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border rounded-3 mb-0">
                            <thead class="table-light fs-12">
                                <tr>
                                    <th>Kategori &amp; Nama Akun</th>
                                    <th>Username / Akun</th>
                                    <th>Password / Rahasia</th>
                                    <th>Host / URL</th>
                                    <th>Catatan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13">
                                @foreach($tenant->credentials as $cred)
                                    <tr>
                                        <td>
                                            <span class="badge {{ $cred->category_badge_class }} mb-1">{{ $cred->category_label }}</span>
                                            <div class="fw-bold text-dark">{{ $cred->label }}</div>
                                        </td>
                                        <td>
                                            @if($cred->username)
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="font-monospace text-dark">{{ $cred->username }}</span>
                                                    <button class="btn btn-sm btn-link text-muted p-0 btn-copy-raw" data-text="{{ $cred->username }}" title="Salin Username">
                                                        <i class="ri-file-copy-line"></i>
                                                    </button>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                                <input type="password" class="form-control font-monospace secret-field" id="val-cred-{{ $cred->id }}" value="••••••••••••" readonly>
                                                <button class="btn btn-outline-secondary btn-reveal-custom" type="button" data-cred-id="{{ $cred->id }}" title="Intip Password">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary btn-copy" type="button" data-target="val-cred-{{ $cred->id }}" title="Salin Password">
                                                    <i class="ri-file-copy-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            @if($cred->url_or_host)
                                                <span class="badge bg-light text-dark border font-monospace fs-11">
                                                    {{ Str::limit($cred->url_or_host, 30) }}
                                                </span>
                                                @if(str_starts_with($cred->url_or_host, 'http://') || str_starts_with($cred->url_or_host, 'https://'))
                                                    <a href="{{ $cred->url_or_host }}" target="_blank" class="text-primary ms-1"><i class="ri-external-link-line"></i></a>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $cred->notes ? Str::limit($cred->notes, 40) : '-' }}</small>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-cred" 
                                                    data-id="{{ $cred->id }}"
                                                    data-category="{{ $cred->category }}"
                                                    data-label="{{ $cred->label }}"
                                                    data-username="{{ $cred->username }}"
                                                    data-url="{{ $cred->url_or_host }}"
                                                    data-notes="{{ $cred->notes }}"
                                                    title="Edit Kredensial">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <form method="POST" action="{{ route('admin.tenants.credentials.destroy', [$tenant->id, $cred->id]) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kredensial {{ $cred->label }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Kredensial">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 border rounded-3 bg-light-subtle">
                        <i class="ri-key-2-line fs-36 text-muted mb-2 d-block"></i>
                        <h6 class="fs-13 fw-semibold text-dark mb-1">Belum Ada Kredensial Tambahan</h6>
                        <p class="fs-12 text-muted mb-3">Catat password root VPS, akun cPanel, Cloudflare, atau kunci API pihak ketiga agar tidak lupa.</p>
                        <button type="button" class="btn btn-sm btn-outline-primary px-3" data-bs-toggle="modal" data-bs-target="#modalAddCredential">
                            <i class="ri-add-line me-1"></i> Tambah Kredensial Pertama
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- TAB 3: 1-KLIK GENERATE .ENV PRODUKSI -->
    <div class="tab-pane fade" id="tab-env">
@php
$hubDomain = config('app.url');
if (empty($hubDomain) || str_contains($hubDomain, 'localhost')) {
    if (request()->getHost() !== 'localhost' && request()->getHost() !== '127.0.0.1') {
        $hubDomain = request()->schemeAndHttpHost();
    } else {
        $hubDomain = 'https://masterhub.nexsphere.web.id';
    }
}
$hubDomain = rtrim($hubDomain, '/');

$subdomain = 'hris';
if ($tenant->domain_frontend) {
    $parts = explode('.', $tenant->domain_frontend);
    if (count($parts) >= 3) {
        $subdomain = $parts[0];
    }
}

$frontendDomain = $tenant->domain_frontend ?: 'hris.nexsphere.web.id';
$backendDomain = $tenant->domain_backend ?: 'api-hris.nexsphere.web.id';
$superadminPass = $tenant->vault?->superadmin_password ?: 'SuperAdmin123!';

$envDocker = <<<EOT
# ========================================================
# NexSphere-HRIS Production Environment (Docker Compose)
# Tenant: {$tenant->company_name} ({$tenant->company_code})
# Generated automatically by NexSphere Master Hub
# ========================================================
ASPNETCORE_ENVIRONMENT=Production

# Company Identity
DEFAULT_COMPANY_CODE={$tenant->company_code}
DEFAULT_COMPANY_NAME="{$tenant->company_name}"
DEFAULT_SUBDOMAIN={$subdomain}

# 1. Database PostgreSQL 16 (Service: postgres)
POSTGRES_DB={$tenant->vault?->db_name}
POSTGRES_USER={$tenant->vault?->db_user}
POSTGRES_PASSWORD={$tenant->vault?->db_password}

# 2. Redis 7 Cache (Service: redis)
REDIS_PASSWORD={$tenant->vault?->redis_password}

# 3. Master Hub Central Control Plane
MASTER_HUB_URL={$hubDomain}
MASTER_HUB_SECRET={$tenant->vault?->master_hub_secret}

# 4. JWT Authentication
JWT_SECRET_KEY={$tenant->vault?->jwt_secret_key}
JWT_ISSUER=https://{$backendDomain}
JWT_AUDIENCE=https://{$frontendDomain}

# 5. ADMS Fingerprint Sync
ADMS_PORT=7788
ADMS_SECRET_TOKEN={$tenant->vault?->adms_secret_token}

# 6. Domain & CORS Routing
DOMAIN_FRONTEND={$frontendDomain}
DOMAIN_BACKEND={$backendDomain}
PUBLIC_API_URL=https://{$backendDomain}/api
CORS_ALLOWED_ORIGINS=https://{$frontendDomain}

# 7. Reset / Seed Password SuperAdmin
SUPERADMIN_SEED_PASSWORD={$superadminPass}
EOT;

$envBareMetal = <<<EOT
# ========================================================
# NexSphere-HRIS Production Environment (Bare-Metal / Non-Docker)
# Tenant: {$tenant->company_name} ({$tenant->company_code})
# ========================================================
ASPNETCORE_ENVIRONMENT=Production

# Company Identity
COMPANY_CODE={$tenant->company_code}
COMPANY_NAME="{$tenant->company_name}"

# Database Configuration (PostgreSQL Bare-metal Localhost)
ConnectionStrings__DefaultConnection="Host=127.0.0.1;Port=5432;Database={$tenant->vault?->db_name};Username={$tenant->vault?->db_user};Password={$tenant->vault?->db_password};SearchPath=public"

# Redis Cache (Bare-metal Localhost)
ConnectionStrings__Redis="127.0.0.1:6379,password={$tenant->vault?->redis_password}"

# Master Hub Central Control Plane
MASTER_HUB_URL={$hubDomain}
MASTER_HUB_SECRET={$tenant->vault?->master_hub_secret}

# JWT Authentication
JwtSettings__SecretKey={$tenant->vault?->jwt_secret_key}
JwtSettings__Issuer=https://{$backendDomain}
JwtSettings__Audience=https://{$frontendDomain}

# ADMS Fingerprint Sync
ADMS_PORT=7788
ADMS_SECRET_TOKEN={$tenant->vault?->adms_secret_token}
EOT;
@endphp

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold fs-15 text-success"><i class="ri-file-copy-2-line me-2"></i>Ekspor Variabel Lingkungan (.env Produksi)</h6>
                        <small class="text-muted fs-12">Konfigurasi siap pakai untuk menghubungkan node VPS klien ke Master Hub & database.</small>
                    </div>
                    <ul class="nav nav-pills" id="pills-env-mode" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active btn-sm py-1 px-3" id="pill-docker-tab" data-bs-toggle="pill" data-bs-target="#pill-docker" type="button" role="tab">
                                <i class="ri-instance-line me-1"></i> Docker Compose (Rekomendasi)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm py-1 px-3" id="pill-baremetal-tab" data-bs-toggle="pill" data-bs-target="#pill-baremetal" type="button" role="tab">
                                <i class="ri-server-line me-1"></i> Bare-Metal (Non-Docker)
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="tab-content" id="pills-envContent">
                    <!-- DOCKER COMPOSE MODE -->
                    <div class="tab-pane fade show active" id="pill-docker" role="tabpanel">
                        <div class="alert alert-info border-0 shadow-sm py-2 px-3 mb-3 d-flex align-items-center justify-content-between">
                            <div class="fs-12">
                                <i class="ri-information-line me-1 text-primary"></i>
                                <strong>Format Modular Docker Compose:</strong> Host database otomatis merujuk ke service internal container <code>postgres</code> & <code>redis</code> (bukan <code>127.0.0.1</code>).
                            </div>
                            <button type="button" class="btn btn-sm btn-success px-3 ms-2 text-nowrap" id="btn-copy-docker-env">
                                <i class="ri-file-copy-line me-1"></i> Salin .env Docker
                            </button>
                        </div>
                        <textarea class="form-control font-monospace fs-12 bg-dark text-light p-3 rounded-3" rows="18" id="env-docker-text" readonly>{{ $envDocker }}</textarea>

                        <div class="mt-3 p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold fs-12 text-secondary mb-2"><i class="ri-terminal-box-line me-1"></i>Cara Cepat Pasang di Terminal VPS:</h6>
                            <div class="bg-dark text-success p-2 rounded font-monospace fs-12 user-select-all mb-2">
                                cd ~/NexSphere-HRIS &amp;&amp; nano .env
                            </div>
                            <small class="text-muted fs-11">
                                Tempelkan teks di atas, simpan (<kbd>Ctrl+O</kbd>, <kbd>Enter</kbd>, <kbd>Ctrl+X</kbd>), lalu jalankan: 
                                <code>docker compose -f docker-compose.prod.yml up -d</code>
                            </small>
                        </div>
                    </div>

                    <!-- BARE-METAL MODE -->
                    <div class="tab-pane fade" id="pill-baremetal" role="tabpanel">
                        <div class="alert alert-warning border-0 shadow-sm py-2 px-3 mb-3 d-flex align-items-center justify-content-between">
                            <div class="fs-12">
                                <i class="ri-alert-line me-1 text-warning"></i>
                                <strong>Format Bare-Metal / Native:</strong> Gunakan format ini hanya jika aplikasi dijalankan langsung sebagai systemd service di OS host (tanpa Docker), di mana PostgreSQL & Redis berjalan di <code>127.0.0.1</code>.
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success px-3 ms-2 text-nowrap" id="btn-copy-baremetal-env">
                                <i class="ri-file-copy-line me-1"></i> Salin .env Bare-Metal
                            </button>
                        </div>
                        <textarea class="form-control font-monospace fs-12 bg-dark text-light p-3 rounded-3" rows="16" id="env-baremetal-text" readonly>{{ $envBareMetal }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB NGINX: KONFIGURASI REVERSE PROXY & SSL -->
    <div class="tab-pane fade" id="tab-nginx">
@php
$confFileName = 'nexsphere-' . strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $tenant->company_code)) . '.conf';

$nginxConfig = <<<EOT
# ========================================================
# Nginx Reverse Proxy for {$tenant->company_name}
# Frontend : {$frontendDomain}
# Backend  : {$backendDomain}
# Generated automatically by NexSphere Master Hub
# ========================================================

# 1. FRONTEND WEB HRIS (Next.js Standalone Portal)
server {
    listen 80;
    server_name {$frontendDomain};

    client_max_body_size 25M;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
}

# 2. BACKEND API (.NET 10 API) & WEBSOCKETS / SIGNALR
server {
    listen 80;
    server_name {$backendDomain};

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;

        proxy_read_timeout 300s;
        proxy_connect_timeout 75s;
    }
}
EOT;
@endphp

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold fs-15 text-info"><i class="ri-global-line me-2"></i>Konfigurasi Nginx Reverse Proxy &amp; SSL</h6>
                    <small class="text-muted fs-12">Virtual Host siap pakai untuk routing domain frontend, backend API, dan sertifikat SSL HTTPS otomatis.</small>
                </div>
                <button type="button" class="btn btn-sm btn-info text-white px-3" id="btn-copy-nginx-conf">
                    <i class="ri-file-copy-line me-1"></i> Salin File Config Nginx
                </button>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 shadow-sm py-2 px-3 mb-3 d-flex align-items-center justify-content-between">
                    <div class="fs-12">
                        <i class="ri-information-line me-1 text-info"></i>
                        File konfigurasi: <code>/etc/nginx/sites-available/{{ $confFileName }}</code>
                    </div>
                </div>

                <textarea class="form-control font-monospace fs-12 bg-dark text-light p-3 rounded-3" rows="18" id="nginx-conf-text" readonly>{{ $nginxConfig }}</textarea>

                <div class="mt-4 p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold fs-13 text-secondary mb-3"><i class="ri-terminal-box-line me-1"></i>3 Langkah Pasang &amp; Auto-SSL di Terminal VPS:</h6>
                    
                    <div class="mb-3">
                        <div class="text-muted fs-11 fw-semibold mb-1">1. Buat &amp; Tempelkan Konfigurasi di VPS:</div>
                        <div class="bg-dark text-success p-2 rounded font-monospace fs-12 user-select-all">
                            sudo nano /etc/nginx/sites-available/{{ $confFileName }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted fs-11 fw-semibold mb-1">2. Aktifkan &amp; Reload Nginx:</div>
                        <div class="bg-dark text-success p-2 rounded font-monospace fs-12 user-select-all">
                            sudo ln -s /etc/nginx/sites-available/{{ $confFileName }} /etc/nginx/sites-enabled/ &amp;&amp; sudo nginx -t &amp;&amp; sudo systemctl reload nginx
                        </div>
                    </div>

                    <div>
                        <div class="text-muted fs-11 fw-semibold mb-1">3. Pasang Sertifikat SSL Gratis (HTTPS Otomatis &amp; Auto-Renew):</div>
                        <div class="bg-dark text-warning p-2 rounded font-monospace fs-12 user-select-all">
                            sudo certbot --nginx -d {{ $frontendDomain }} -d {{ $backendDomain }}
                        </div>
                        <small class="text-muted fs-11 mt-1 d-block">
                            *Certbot otomatis menyisipkan sertifikat SSL ke Nginx, membuat redirect HTTP ke HTTPS, dan memperpanjang otomatis seumur hidup.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: TELEMETRI VPS NODE -->
    <div class="tab-pane fade" id="tab-telemetry">
        <!-- 1. LIVE NODE STATUS CARD (1 SERVER AKTIF) -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-transparent border-bottom py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold fs-15 text-primary">
                            <i class="ri-server-line me-2"></i>Status Realtime VPS Node HRIS (1 Server Terhubung)
                        </h6>
                        <small class="text-muted fs-12">Kondisi operasional terkini dari 1 VPS klien. Diperbarui otomatis (realtime) tanpa membebani server.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-secondary border font-monospace fs-11" id="live-last-check-badge">
                            <i class="ri-time-line me-1"></i>Dicek: <span id="live-last-check-text">{{ now()->format('H:i:s') }}</span>
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-11">
                            <span class="spinner-grow spinner-grow-sm me-1" style="width: 0.4rem; height: 0.4rem;" role="status"></span>Auto-Sync (30s)
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-primary px-3" id="btn-refresh-telemetry" title="Segarkan Data Realtime Sekarang">
                            <i class="ri-refresh-line me-1" id="icon-refresh-telemetry"></i> Segarkan
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- 1. IDENTITAS & STATUS SERVER -->
                    <div class="col-md-3">
                        <div class="p-3 border rounded-3 bg-light-subtle h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">Status Server Node</span>
                                <span id="live-status-pill" class="badge {{ $nodeHealth === 'healthy' ? 'bg-success' : ($nodeHealth === 'warning' ? 'bg-warning text-dark' : ($latestTele ? 'bg-danger' : 'bg-light text-muted border')) }} fs-11 px-2 py-1">
                                    <i class="{{ $nodeHealth === 'healthy' ? 'ri-checkbox-circle-line' : ($nodeHealth === 'warning' ? 'ri-alert-line' : 'ri-close-circle-line') }} me-1" id="live-status-icon"></i>
                                    <span id="live-status-text">{{ $nodeHealth === 'healthy' ? 'ONLINE' : ($nodeHealth === 'warning' ? 'WARNING' : ($latestTele ? 'OFFLINE' : 'STANDBY')) }}</span>
                                </span>
                            </div>
                            <div class="mt-2">
                                <div class="text-muted fs-11">Host VPS / IP Address:</div>
                                <div class="fw-bold font-monospace fs-14 text-dark">{{ $tenant->vault?->vps_ip_address ?? '202.155.94.108' }}</div>
                            </div>
                            <div class="mt-2 fs-11 text-muted">
                                <div>Provider: <strong>{{ $tenant->vault?->vps_provider ?? 'Domainesia' }}</strong></div>
                                <div>Versi App: <span class="badge bg-light text-dark font-monospace" id="live-app-version">v{{ $latestTele?->app_version ?? '1.0.0' }}</span></div>
                                <div class="mt-1">Uptime: <span class="font-monospace fw-semibold text-dark" id="live-uptime-text">{{ $latestTele ? gmdate("H:i:s", $latestTele->uptime_seconds) : '-' }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. PENGGUNAAN RAM -->
                    <div class="col-md-3">
                        <div class="p-3 border rounded-3 bg-light-subtle h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">
                                <i class="ri-pie-chart-2-line me-1 text-info"></i>Penggunaan RAM
                            </div>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-26 fw-bold text-info" id="live-ram-text">{{ $latestTele?->ram_usage_percent ?? 0 }}</span>
                                <span class="fs-14 fw-semibold text-muted">%</span>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar {{ ($latestTele?->ram_usage_percent ?? 0) > 85 ? 'bg-danger' : 'bg-info' }}" id="live-ram-bar" role="progressbar" style="width: {{ min(100, $latestTele?->ram_usage_percent ?? 0) }}%;"></div>
                            </div>
                            <small class="text-muted fs-11 mt-2 d-block">Alokasi memori sistem &amp; kontainer Docker</small>
                        </div>
                    </div>

                    <!-- 3. BEBAN CPU -->
                    <div class="col-md-3">
                        <div class="p-3 border rounded-3 bg-light-subtle h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">
                                <i class="ri-cpu-line me-1 text-primary"></i>Beban CPU
                            </div>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-26 fw-bold text-primary" id="live-cpu-text">{{ $latestTele?->cpu_usage_percent ?? 0 }}</span>
                                <span class="fs-14 fw-semibold text-muted">%</span>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar {{ ($latestTele?->cpu_usage_percent ?? 0) > 85 ? 'bg-danger' : 'bg-primary' }}" id="live-cpu-bar" role="progressbar" style="width: {{ min(100, $latestTele?->cpu_usage_percent ?? 0) }}%;"></div>
                            </div>
                            <small class="text-muted fs-11 mt-2 d-block">Beban proses API, query DB &amp; worker</small>
                        </div>
                    </div>

                    <!-- 4. DATABASE & KARYAWAN -->
                    <div class="col-md-3">
                        <div class="p-3 border rounded-3 bg-light-subtle h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                <i class="ri-database-2-line me-1 text-success"></i>Kesehatan Database
                            </div>
                            <div id="live-db-container">
                                @if($latestTele?->db_healthy)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-12" id="live-db-badge">
                                        <i class="ri-check-line me-1"></i>Database Sehat (Healthy)
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-12" id="live-db-badge">
                                        <i class="ri-alert-line me-1"></i>Database Terkendala
                                    </span>
                                @endif
                            </div>
                            <div class="fs-13 fw-semibold text-dark mt-2">
                                <i class="ri-team-line me-1 text-muted"></i><span id="live-emp-count">{{ $latestTele?->total_employees ?? 0 }}</span> Karyawan Terdaftar
                            </div>
                            <small class="text-muted fs-11 mt-1 d-block">
                                Laporan: <strong id="live-reported-ago">{{ $latestTele ? $latestTele->reported_at->diffForHumans() : '-' }}</strong> (<span id="live-reported-at">{{ $latestTele ? $latestTele->reported_at->format('H:i:s') : '' }}</span>)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. DETAIL SPESIFIKASI & DIAGNOSTIK NODE VPS (1 SERVER AKTIF) -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold fs-15 text-secondary">
                            <i class="ri-list-check me-2 text-primary"></i>Rincian Diagnostik &amp; Spesifikasi Node VPS
                        </h6>
                        <small class="text-muted fs-12">Parameter konektivitas dan kesehatan layanan pada VPS produksi klien.</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 fs-12">
                        <i class="ri-shield-check-line me-1"></i>Zero Bloat: 1 Data Terkini
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0 fs-13">
                        <tbody>
                            <tr>
                                <th class="table-light text-muted fw-semibold" style="width: 25%;">Instance Server</th>
                                <td class="fw-semibold text-dark">
                                    <i class="ri-server-line me-1 text-primary"></i> NexSphere Production Node ({{ $tenant->company_code }})
                                </td>
                                <th class="table-light text-muted fw-semibold" style="width: 25%;">Host / IP VPS</th>
                                <td class="font-monospace fw-bold text-dark">
                                    {{ $tenant->vault?->vps_ip_address ?? '202.155.94.108' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="table-light text-muted fw-semibold">Penyedia VPS</th>
                                <td>{{ $tenant->vault?->vps_provider ?? 'Domainesia' }} (SSH Port {{ $tenant->vault?->ssh_port ?? 22 }})</td>
                                <th class="table-light text-muted fw-semibold">Versi Aplikasi HRIS</th>
                                <td><span class="badge bg-dark font-monospace" id="spec-app-version">v{{ $latestTele?->app_version ?? '1.0.0' }}</span></td>
                            </tr>
                            <tr>
                                <th class="table-light text-muted fw-semibold">Status Operasional Node</th>
                                <td>
                                    @if($nodeHealth === 'healthy')
                                        <span class="badge bg-success" id="spec-status-badge">
                                            Aktif & Merespons
                                        </span>
                                    @elseif($nodeHealth === 'warning')
                                        <span class="badge bg-warning text-dark" id="spec-status-badge">
                                            Warning (Beban Tinggi / Respons Tertunda)
                                        </span>
                                    @elseif($latestTele)
                                        <span class="badge bg-danger" id="spec-status-badge">
                                            Offline (> 15 Menit)
                                        </span>
                                    @else
                                        <span class="badge bg-secondary" id="spec-status-badge">
                                            Belum Terhubung
                                        </span>
                                    @endif
                                </td>
                                <th class="table-light text-muted fw-semibold">Uptime Server</th>
                                <td class="font-monospace fw-semibold" id="spec-uptime">{{ $latestTele ? gmdate("H:i:s", $latestTele->uptime_seconds) : '-' }}</td>
                            </tr>
                            <tr>
                                <th class="table-light text-muted fw-semibold">Database Engine</th>
                                <td>
                                    <span class="badge {{ ($latestTele?->db_healthy ?? false) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border' }}" id="spec-db-badge">
                                        <i class="ri-check-line me-1"></i>PostgreSQL 16 (Healthy)
                                    </span>
                                    <span class="text-muted ms-2 fs-12 font-monospace">{{ $tenant->vault?->db_name }}</span>
                                </td>
                                <th class="table-light text-muted fw-semibold">Cache Engine</th>
                                <td>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="ri-flashlight-line me-1"></i>Redis 7 Alpine
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="table-light text-muted fw-semibold">Beban Penggunaan Memori</th>
                                <td>
                                    <strong class="text-info fs-14" id="spec-ram">{{ $latestTele?->ram_usage_percent ?? 0 }}%</strong>
                                    <span class="text-muted fs-11 ms-1">dari total alokasi VPS</span>
                                </td>
                                <th class="table-light text-muted fw-semibold">Beban Komputasi CPU</th>
                                <td>
                                    <strong class="text-primary fs-14" id="spec-cpu">{{ $latestTele?->cpu_usage_percent ?? 0 }}%</strong>
                                    <span class="text-muted fs-11 ms-1">rata-rata load proses</span>
                                </td>
                            </tr>
                            <tr>
                                <th class="table-light text-muted fw-semibold">Total Karyawan di DB</th>
                                <td class="fw-bold"><span id="spec-emp">{{ $latestTele?->total_employees ?? 0 }}</span> Karyawan Terdaftar</td>
                                <th class="table-light text-muted fw-semibold">Sinkronisasi Terakhir</th>
                                <td>
                                    <span id="spec-reported-ago" class="fw-semibold text-dark">{{ $latestTele ? $latestTele->reported_at->diffForHumans() : '-' }}</span>
                                    <span class="text-muted fs-11 font-monospace" id="spec-reported-at">({{ $latestTele ? $latestTele->reported_at->format('d M Y, H:i:s') : '' }})</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light-subtle py-2 px-3 border-top d-flex align-items-center justify-content-between">
                <small class="text-muted fs-11">
                    <i class="ri-database-2-line me-1 text-primary"></i><strong>Kebijakan Database:</strong> Menggunakan teknik <em>Upsert / Update Data</em> — record selalu ditimpa ke 1 baris sehingga kapasitas database Master Hub tetap bersih, stabil, dan bebas dari penumpukan data selamanya.
                </small>
            </div>
        </div>
    </div>

    <!-- TAB 5: AUDIT LOGS -->
    <div class="tab-pane fade" id="tab-audit">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold fs-15"><i class="ri-shield-check-line me-2 text-primary"></i>Jejak Audit Akses Data & Kredensial</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-12">
                            <tr>
                                <th>Waktu</th>
                                <th>Operator Admin</th>
                                <th>Aktivitas</th>
                                <th>IP Address & Client</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13">
                            @forelse($auditLogs as $log)
                                <tr>
                                    <td>
                                        <strong>{{ $log->created_at->format('d M Y, H:i') }}</strong>
                                        <div class="text-muted fs-11">{{ $log->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td><strong class="text-primary">{{ $log->user?->name ?? 'Sistem' }}</strong></td>
                                    <td>{{ $log->description ?: $log->action }}</td>
                                    <td><span class="badge bg-light text-muted font-monospace">{{ $log->ip_address }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada aktivitas tercatat pada tenant ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: TAMBAH KREDENSIAL BARU -->
<div class="modal fade" id="modalAddCredential" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold fs-15 text-primary">
                    <i class="ri-key-2-line me-1"></i> Tambah Kredensial Baru ke Brankas
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.tenants.credentials.store', $tenant->id) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Kategori <span class="text-danger">*</span></label>
                            <select name="category" class="form-select form-select-sm" required>
                                <option value="server">Server / VPS (SSH, Root)</option>
                                <option value="hosting">cPanel / Hosting / Web Admin</option>
                                <option value="database">Database (PostgreSQL, MySQL, Redis)</option>
                                <option value="api">API Key &amp; Token (Cloudflare, dll)</option>
                                <option value="email">Email / SMTP Relay</option>
                                <option value="other">Kredensial Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Judul / Nama Akun <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control form-control-sm" placeholder="cth: Password Root VPS Domainesia" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Username / Email Akun</label>
                            <input type="text" name="username" class="form-control form-control-sm" placeholder="cth: root atau admin@client.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Host / IP / URL Login</label>
                            <input type="text" name="url_or_host" class="form-control form-control-sm" placeholder="cth: 202.155.94.108 atau https://...">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-12">Password / Secret Key <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="secret_value" id="add-cred-secret" class="form-control font-monospace" placeholder="Masukkan password atau token rahasia" required>
                                <button type="button" class="btn btn-outline-secondary" id="btn-generate-add-secret" title="Generate Password Kuat Acak">
                                    <i class="ri-magic-line me-1"></i> Acak
                                </button>
                            </div>
                            <small class="text-muted fs-11">Akan dienkripsi secara otomatis menggunakan AES-256 At-Rest Encryption.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-12">Catatan Khusus (Opsional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Catatan port khusus, lokasi file key, instruksi login..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold px-4">
                        <i class="ri-save-line me-1"></i> Simpan ke Brankas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT KREDENSIAL -->
<div class="modal fade" id="modalEditCredential" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold fs-15 text-primary">
                    <i class="ri-edit-line me-1"></i> Perbarui Kredensial
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEditCredential" action="">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Kategori <span class="text-danger">*</span></label>
                            <select name="category" id="edit-cred-category" class="form-select form-select-sm" required>
                                <option value="server">Server / VPS (SSH, Root)</option>
                                <option value="hosting">cPanel / Hosting / Web Admin</option>
                                <option value="database">Database (PostgreSQL, MySQL, Redis)</option>
                                <option value="api">API Key &amp; Token (Cloudflare, dll)</option>
                                <option value="email">Email / SMTP Relay</option>
                                <option value="other">Kredensial Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Judul / Nama Akun <span class="text-danger">*</span></label>
                            <input type="text" name="label" id="edit-cred-label" class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Username / Email Akun</label>
                            <input type="text" name="username" id="edit-cred-username" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-12">Host / IP / URL Login</label>
                            <input type="text" name="url_or_host" id="edit-cred-url" class="form-control form-control-sm">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-12">Password Baru (Opsional)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="secret_value" id="edit-cred-secret" class="form-control font-monospace" placeholder="Biarkan kosong jika tidak ingin mengubah password">
                                <button type="button" class="btn btn-outline-secondary" id="btn-generate-edit-secret" title="Generate Password Kuat Acak">
                                    <i class="ri-magic-line me-1"></i> Acak
                                </button>
                            </div>
                            <small class="text-muted fs-11">Kosongkan jika password saat ini tidak berubah.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-12">Catatan Khusus (Opsional)</label>
                            <textarea name="notes" id="edit-cred-notes" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold px-4">
                        <i class="ri-check-line me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Reveal secret via AJAX
    document.querySelectorAll('.btn-reveal').forEach(btn => {
        btn.addEventListener('click', function () {
            const field = this.getAttribute('data-field');
            const input = document.getElementById('val-' + field);

            if (input.type === 'text') {
                input.type = 'password';
                this.innerHTML = '<i class="ri-eye-line"></i> Intip';
                return;
            }

            fetch('{{ route("admin.tenants.vault.reveal", $tenant->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ field: field })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.type = 'text';
                    input.value = data.value;
                    this.innerHTML = '<i class="ri-eye-off-line"></i> Tutup';
                    if (window.toastSuccess) window.toastSuccess('Kredensial dibuka & dicatat ke audit log.');
                } else {
                    alert('Gagal membuka kredensial: ' + data.message);
                }
            })
            .catch(err => alert('Terjadi kesalahan jaringan'));
        });
    });

    // Copy single field
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            navigator.clipboard.writeText(input.value).then(() => {
                if (window.toastSuccess) {
                    window.toastSuccess('Berhasil disalin ke clipboard!');
                } else {
                    alert('Berhasil disalin ke clipboard!');
                }
            });
        });
    });

    // Copy Docker .env
    const copyDockerBtn = document.getElementById('btn-copy-docker-env');
    if (copyDockerBtn) {
        copyDockerBtn.addEventListener('click', function () {
            const textarea = document.getElementById('env-docker-text');
            navigator.clipboard.writeText(textarea.value).then(() => {
                if (window.toastSuccess) {
                    window.toastSuccess('Konfigurasi .env (Docker Compose) berhasil disalin!');
                } else {
                    alert('Konfigurasi .env (Docker Compose) berhasil disalin!');
                }
            });
        });
    }

    // Copy Bare-Metal .env
    const copyBaremetalBtn = document.getElementById('btn-copy-baremetal-env');
    if (copyBaremetalBtn) {
        copyBaremetalBtn.addEventListener('click', function () {
            const textarea = document.getElementById('env-baremetal-text');
            navigator.clipboard.writeText(textarea.value).then(() => {
                if (window.toastSuccess) {
                    window.toastSuccess('Konfigurasi .env (Bare-Metal) berhasil disalin!');
                } else {
                    alert('Konfigurasi .env (Bare-Metal) berhasil disalin!');
                }
            });
        });
    }

    // Copy Nginx Config
    const copyNginxBtn = document.getElementById('btn-copy-nginx-conf');
    if (copyNginxBtn) {
        copyNginxBtn.addEventListener('click', function () {
            const textarea = document.getElementById('nginx-conf-text');
            navigator.clipboard.writeText(textarea.value).then(() => {
                if (window.toastSuccess) {
                    window.toastSuccess('Konfigurasi Nginx berhasil disalin!');
                } else {
                    alert('Konfigurasi Nginx berhasil disalin!');
                }
            });
        });
    }

    // Copy raw text helper (e.g. username)
    document.querySelectorAll('.btn-copy-raw').forEach(btn => {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-text');
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                if (window.toastSuccess) {
                    window.toastSuccess('Username disalin ke clipboard!');
                } else {
                    alert('Disalin: ' + text);
                }
            });
        });
    });

    // Reveal custom credential secret via AJAX with UU PDP Audit Log
    document.querySelectorAll('.btn-reveal-custom').forEach(btn => {
        btn.addEventListener('click', function () {
            const credId = this.getAttribute('data-cred-id');
            const input = document.getElementById('val-cred-' + credId);

            if (input.type === 'text') {
                input.type = 'password';
                this.innerHTML = '<i class="ri-eye-line"></i>';
                return;
            }

            fetch('{{ url("admin/tenants/" . $tenant->id . "/credentials") }}/' + credId + '/reveal', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.type = 'text';
                    input.value = data.value;
                    this.innerHTML = '<i class="ri-eye-off-line"></i>';
                    if (window.toastSuccess) window.toastSuccess('Password dibuka & dicatat ke Audit Log.');
                } else {
                    alert('Gagal membuka password: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(() => alert('Terjadi kesalahan jaringan'));
        });
    });

    // Edit Custom Credential Modal setup
    const modalEditEl = document.getElementById('modalEditCredential');
    const modalEditCred = modalEditEl ? new bootstrap.Modal(modalEditEl) : null;
    document.querySelectorAll('.btn-edit-cred').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const form = document.getElementById('formEditCredential');
            form.action = '{{ url("admin/tenants/" . $tenant->id . "/credentials") }}/' + id;

            document.getElementById('edit-cred-category').value = this.getAttribute('data-category') || 'server';
            document.getElementById('edit-cred-label').value = this.getAttribute('data-label') || '';
            document.getElementById('edit-cred-username').value = this.getAttribute('data-username') || '';
            document.getElementById('edit-cred-url').value = this.getAttribute('data-url') || '';
            document.getElementById('edit-cred-notes').value = this.getAttribute('data-notes') || '';
            document.getElementById('edit-cred-secret').value = '';

            if (modalEditCred) modalEditCred.show();
        });
    });

    // Random Strong Password Generator
    function generateStrongSecret() {
        const charset = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%^&*()_+-=";
        let result = "";
        for (let i = 0; i < 18; i++) {
            result += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        return result;
    }

    document.getElementById('btn-generate-add-secret')?.addEventListener('click', function () {
        document.getElementById('add-cred-secret').value = generateStrongSecret();
    });

    document.getElementById('btn-generate-edit-secret')?.addEventListener('click', function () {
        document.getElementById('edit-cred-secret').value = generateStrongSecret();
    });

    // ----------------------------------------------------
    // REALTIME TELEMETRI VPS NODE (Ultra-Lightweight Auto-Sync)
    // ----------------------------------------------------
    const liveTelemetryUrl = '{{ route("admin.tenants.telemetry.live", $tenant->id) }}';
    let isFetchingTelemetry = false;

    function updateLiveTelemetryUI(data) {
        if (!data || !data.has_data) return;

        // 1. Status Pill & Badge
        const statusPill = document.getElementById('live-status-pill');
        const statusText = document.getElementById('live-status-text');
        const statusIcon = document.getElementById('live-status-icon');
        const tabBadge = document.getElementById('tab-telemetry-badge');

        if (data.health_status === 'healthy') {
            if (statusPill) {
                statusPill.className = 'badge bg-success fs-11 px-2 py-1';
                if (statusIcon) statusIcon.className = 'ri-checkbox-circle-line me-1';
                if (statusText) statusText.textContent = 'ONLINE';
            }
            if (tabBadge) {
                tabBadge.className = 'badge bg-success-subtle text-success border border-success-subtle ms-1';
                tabBadge.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1 text-success" style="width: 0.45rem; height: 0.45rem;" role="status"></span>Online';
            }
        } else if (data.health_status === 'warning') {
            if (statusPill) {
                statusPill.className = 'badge bg-warning text-dark fs-11 px-2 py-1';
                if (statusIcon) statusIcon.className = 'ri-alert-line me-1';
                if (statusText) statusText.textContent = 'WARNING';
            }
            if (tabBadge) {
                tabBadge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1';
                tabBadge.innerHTML = '<i class="ri-alert-line me-1 text-warning"></i>Warning';
            }
        } else {
            if (statusPill) {
                statusPill.className = 'badge bg-danger fs-11 px-2 py-1';
                if (statusIcon) statusIcon.className = 'ri-close-circle-line me-1';
                if (statusText) statusText.textContent = 'OFFLINE';
            }
            if (tabBadge) {
                tabBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle ms-1';
                tabBadge.textContent = 'Offline';
            }
        }

        // 2. RAM Usage
        const ramText = document.getElementById('live-ram-text');
        const ramBar = document.getElementById('live-ram-bar');
        if (ramText) ramText.textContent = data.ram_usage_percent;
        if (ramBar) {
            ramBar.style.width = Math.min(100, data.ram_usage_percent) + '%';
            ramBar.className = 'progress-bar ' + (data.ram_usage_percent > 85 ? 'bg-danger' : 'bg-info');
        }

        // 3. CPU Usage
        const cpuText = document.getElementById('live-cpu-text');
        const cpuBar = document.getElementById('live-cpu-bar');
        if (cpuText) cpuText.textContent = data.cpu_usage_percent;
        if (cpuBar) {
            cpuBar.style.width = Math.min(100, data.cpu_usage_percent) + '%';
            cpuBar.className = 'progress-bar ' + (data.cpu_usage_percent > 85 ? 'bg-danger' : 'bg-primary');
        }

        // 4. Database Health
        const dbBadge = document.getElementById('live-db-badge');
        if (dbBadge) {
            if (data.db_healthy) {
                dbBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-12';
                dbBadge.innerHTML = '<i class="ri-check-line me-1"></i>Database Sehat (Healthy)';
            } else {
                dbBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-12';
                dbBadge.innerHTML = '<i class="ri-alert-line me-1"></i>Database Terkendala';
            }
        }

        // 5. Total Employees & Uptime
        const empCount = document.getElementById('live-emp-count');
        const uptimeText = document.getElementById('live-uptime-text');
        const appVersion = document.getElementById('live-app-version');
        const reportedAgo = document.getElementById('live-reported-ago');
        const reportedAt = document.getElementById('live-reported-at');
        const lastCheckText = document.getElementById('live-last-check-text');

        if (empCount) empCount.textContent = data.total_employees;
        if (uptimeText) uptimeText.textContent = data.uptime_formatted;
        if (appVersion) appVersion.textContent = 'v' + data.app_version;
        if (reportedAgo) reportedAgo.textContent = data.reported_ago;
        if (reportedAt) reportedAt.textContent = data.reported_at;
        if (lastCheckText) lastCheckText.textContent = data.last_check;

        // 6. Specification Table Sync
        const specAppVersion = document.getElementById('spec-app-version');
        const specStatusBadge = document.getElementById('spec-status-badge');
        const specUptime = document.getElementById('spec-uptime');
        const specDbBadge = document.getElementById('spec-db-badge');
        const specRam = document.getElementById('spec-ram');
        const specCpu = document.getElementById('spec-cpu');
        const specEmp = document.getElementById('spec-emp');
        const specReportedAgo = document.getElementById('spec-reported-ago');
        const specReportedAt = document.getElementById('spec-reported-at');

        if (specAppVersion) specAppVersion.textContent = 'v' + data.app_version;
        if (specStatusBadge) {
            specStatusBadge.className = 'badge ' + (data.online ? 'bg-success' : 'bg-secondary');
            specStatusBadge.textContent = data.online ? 'Aktif & Merespons' : 'Tidak Ada Respons (> 5 Menit)';
        }
        if (specUptime) specUptime.textContent = data.uptime_formatted;
        if (specDbBadge) {
            specDbBadge.className = 'badge ' + (data.db_healthy ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border');
            specDbBadge.innerHTML = data.db_healthy ? '<i class="ri-check-line me-1"></i>PostgreSQL 16 (Healthy)' : '<i class="ri-alert-line me-1"></i>PostgreSQL 16 (Terkendala)';
        }
        if (specRam) specRam.textContent = data.ram_usage_percent + '%';
        if (specCpu) specCpu.textContent = data.cpu_usage_percent + '%';
        if (specEmp) specEmp.textContent = data.total_employees;
        if (specReportedAgo) specReportedAgo.textContent = data.reported_ago;
        if (specReportedAt) specReportedAt.textContent = '(' + data.reported_at + ')';
    }

    function syncLiveTelemetry(showSpinner = false) {
        if (isFetchingTelemetry) return;
        isFetchingTelemetry = true;

        const refreshIcon = document.getElementById('icon-refresh-telemetry');
        if (showSpinner && refreshIcon) refreshIcon.style.animation = 'spin 0.8s linear infinite';

        fetch(liveTelemetryUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            updateLiveTelemetryUI(data);
        })
        .catch(err => {
            console.warn('Realtime telemetry fetch notice:', err);
        })
        .finally(() => {
            isFetchingTelemetry = false;
            if (showSpinner && refreshIcon) refreshIcon.style.animation = '';
        });
    }

    // Manual refresh button click
    document.getElementById('btn-refresh-telemetry')?.addEventListener('click', function () {
        syncLiveTelemetry(true);
    });

    // Auto-sync interval (every 30 seconds)
    // Only runs when browser window is visible and telemetry tab is active
    setInterval(function () {
        if (document.visibilityState === 'visible') {
            const telemetryPane = document.getElementById('tab-telemetry');
            if (telemetryPane && telemetryPane.classList.contains('active')) {
                syncLiveTelemetry(false);
            }
        }
    }, 30000);
});
</script>
<style>
@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>
@endpush

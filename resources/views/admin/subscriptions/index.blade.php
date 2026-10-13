@extends('admin.layouts.master')

@section('title', 'Status Langganan Klien | NexSphere Master Hub')
@section('pagetitle', 'Langganan & Lisensi Tenant')
@section('title-sub', 'Subscriptions')

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-shield-check-line me-2"></i>Daftar Seluruh Kontrak & Langganan</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-light' }}">Semua</a>
            <a href="{{ route('admin.subscriptions.index', ['status' => 'active']) }}" class="btn btn-sm {{ request('status') === 'active' ? 'btn-success' : 'btn-light' }}">Aktif</a>
            <a href="{{ route('admin.subscriptions.index', ['status' => 'grace_period']) }}" class="btn btn-sm {{ request('status') === 'grace_period' ? 'btn-warning' : 'btn-light' }}">Masa Tenggang</a>
            <a href="{{ route('admin.subscriptions.index', ['status' => 'expired']) }}" class="btn btn-sm {{ request('status') === 'expired' ? 'btn-danger' : 'btn-light' }}">Kedaluwarsa</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-12">
                    <tr>
                        <th>Perusahaan / Tenant</th>
                        <th>Paket Terpilih</th>
                        <th>Periode Kontrak</th>
                        <th>Siklus Tagihan</th>
                        <th>Status</th>
                        <th class="text-end">Aksi Masa Tenggang</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse($subscriptions as $sub)
                        <tr>
                            <td>
                                <a href="{{ route('admin.tenants.show', $sub->tenant_id) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $sub->tenant?->company_name ?? 'N/A' }}
                                </a>
                                <div class="text-muted fs-11 font-monospace">{{ $sub->tenant?->company_code }}</div>
                            </td>
                            <td>
                                <strong class="text-primary">{{ $sub->package?->name ?? 'Custom' }}</strong>
                                <small class="text-muted fs-11 d-block">Maks: {{ $sub->getEffectiveMaxEmployees() }} Pegawai</small>
                            </td>
                            <td>
                                <div>{{ $sub->start_date->format('d M Y') }} - <strong>{{ $sub->end_date->format('d M Y') }}</strong></div>
                                @if($sub->status === 'grace_period' && $sub->grace_period_until)
                                    <small class="badge bg-warning-subtle text-warning border border-warning-subtle fs-11">
                                        Grace until: {{ $sub->grace_period_until->format('d M Y') }}
                                    </small>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-secondary">{{ strtoupper($sub->billing_cycle) }}</span></td>
                            <td>
                                @if($sub->status === 'active')
                                    <span class="badge bg-success">Aktif</span>
                                @elseif($sub->status === 'grace_period')
                                    <span class="badge bg-warning text-dark">Grace Period</span>
                                @elseif($sub->status === 'expired')
                                    <span class="badge bg-danger">Expired</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($sub->status) }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#graceModal-{{ $sub->id }}">
                                    <i class="ri-timer-flash-line me-1"></i> Grace Period
                                </button>

                                <!-- Grace Period Modal -->
                                <div class="modal fade text-start" id="graceModal-{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow rounded-3">
                                            <div class="modal-header border-bottom py-3">
                                                <h6 class="modal-title fw-bold fs-15 text-warning"><i class="ri-timer-line me-1"></i>Beri Perpanjangan Masa Tenggang</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.subscriptions.extend-grace', $sub->id) }}">
                                                @csrf
                                                <div class="modal-body p-4">
                                                    <p class="fs-13 text-muted">
                                                        Berikan waktu kelonggaran bagi <strong>{{ $sub->tenant?->company_name }}</strong> untuk melakukan pembayaran tanpa memutus akses HRIS mereka.
                                                    </p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold fs-13">Tambahan Hari Masa Tenggang</label>
                                                        <input type="number" name="days" class="form-control" value="7" min="1" max="60" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top py-2">
                                                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">Simpan Masa Tenggang</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada data langganan tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($subscriptions->hasPages())
        <div class="card-footer bg-transparent border-top py-3">
            {{ $subscriptions->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

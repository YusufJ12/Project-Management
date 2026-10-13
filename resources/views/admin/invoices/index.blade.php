@extends('admin.layouts.master')

@section('title', 'Invoices & Tagihan Klien | NexSphere Master Hub')
@section('pagetitle', 'Invoices & Billing SaaS')
@section('title-sub', 'Keuangan')

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-file-list-3-line me-2"></i>Daftar Tagihan Lisensi</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.invoices.index') }}" class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-light' }}">Semua</a>
            <a href="{{ route('admin.invoices.index', ['status' => 'unpaid']) }}" class="btn btn-sm {{ request('status') === 'unpaid' ? 'btn-warning' : 'btn-light' }}">Belum Bayar</a>
            <a href="{{ route('admin.invoices.index', ['status' => 'paid']) }}" class="btn btn-sm {{ request('status') === 'paid' ? 'btn-success' : 'btn-light' }}">Lunas</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-12">
                    <tr>
                        <th>Nomor Invoice</th>
                        <th>Perusahaan / Tenant</th>
                        <th>Nominal Tagihan</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th class="text-end">Aksi Pembayaran</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse($invoices as $inv)
                        <tr>
                            <td>
                                <strong class="font-monospace text-dark">{{ $inv->invoice_number }}</strong>
                                <small class="text-muted fs-11 d-block">{{ $inv->created_at->format('d M Y') }}</small>
                            </td>
                            <td>
                                <a href="{{ route('admin.tenants.show', $inv->tenant_id) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $inv->tenant?->company_name ?? 'N/A' }}
                                </a>
                            </td>
                            <td><strong class="text-success fs-14">{{ $inv->formatted_amount }}</strong></td>
                            <td>{{ $inv->due_date->format('d M Y') }}</td>
                            <td>
                                @if($inv->payment_status === 'paid')
                                    <span class="badge bg-success">Lunas</span>
                                    <small class="text-muted fs-10 d-block">{{ $inv->paid_at ? $inv->paid_at->format('d/m/y H:i') : '' }}</small>
                                @elseif($inv->payment_status === 'unpaid')
                                    <span class="badge bg-warning text-dark">Belum Dibayar</span>
                                @elseif($inv->payment_status === 'overdue')
                                    <span class="badge bg-danger">Terlambat</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($inv->payment_status) }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($inv->payment_status !== 'paid')
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal-{{ $inv->id }}">
                                        <i class="ri-check-double-line me-1"></i> Tandai Lunas
                                    </button>

                                    <!-- Pay Modal -->
                                    <div class="modal fade text-start" id="payModal-{{ $inv->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow rounded-3">
                                                <div class="modal-header border-bottom py-3">
                                                    <h6 class="modal-title fw-bold fs-15 text-success">Konfirmasi Pembayaran Invoice</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="{{ route('admin.invoices.pay', $inv->id) }}">
                                                    @csrf
                                                    <div class="modal-body p-4">
                                                        <p class="fs-13 text-muted">
                                                            Konfirmasi penerimaan pembayaran sebesar <strong>{{ $inv->formatted_amount }}</strong> untuk <strong>{{ $inv->tenant?->company_name }}</strong>.
                                                        </p>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold fs-13">Metode Pembayaran</label>
                                                            <select name="payment_method" class="form-select" required>
                                                                <option value="bank_transfer">Transfer Bank (BCA / Mandiri / BNI)</option>
                                                                <option value="qris">QRIS / E-Wallet</option>
                                                                <option value="midtrans">Payment Gateway (Midtrans)</option>
                                                                <option value="manual">Tunai / Manual</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold fs-13">Nomor Referensi / Bukti Transfer</label>
                                                            <input type="text" name="payment_reference" class="form-control" placeholder="Contoh: TRF-8392019482">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top py-2">
                                                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-success btn-sm fw-semibold">Konfirmasi Lunas</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted fs-12"><i class="ri-check-line text-success"></i> Selesai</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada tagihan invoice tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
        <div class="card-footer bg-transparent border-top py-3">
            {{ $invoices->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

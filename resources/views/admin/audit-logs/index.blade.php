@extends('admin.layouts.master')

@section('title', 'Jejak Audit Brankas (UU PDP) | NexSphere Master Hub')
@section('pagetitle', 'Jejak Audit Keamanan & Kredensial')
@section('title-sub', 'Kepatuhan UU PDP')

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold fs-15 text-primary"><i class="ri-shield-user-fill me-2 text-danger"></i>Log Akses Brankas Kredensial</h6>
            <small class="text-muted fs-12">Catatan forensik seluruh pembukaan password, perubahan status, dan tindakan operator.</small>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-12">
                    <tr>
                        <th>Waktu Kejadian</th>
                        <th>Operator Admin</th>
                        <th>Aksi</th>
                        <th>Deskripsi Aktivitas</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                <strong>{{ $log->created_at->format('d M Y, H:i:s') }}</strong>
                                <small class="text-muted fs-11 d-block">{{ $log->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <strong class="text-dark">{{ $log->user?->name ?? 'Sistem / Anonim' }}</strong>
                                <small class="text-muted fs-11 d-block">{{ $log->user?->email }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary font-monospace border fs-11">{{ $log->action }}</span>
                            </td>
                            <td>{{ $log->description ?: '-' }}</td>
                            <td><span class="font-monospace text-muted fs-12">{{ $log->ip_address }}</span></td>
                            <td><span class="text-muted fs-11 text-truncate d-inline-block" style="max-width: 200px;" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat audit keamanan tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-transparent border-top py-3">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

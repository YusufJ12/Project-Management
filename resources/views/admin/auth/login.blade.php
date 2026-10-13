@extends('admin.layouts.master_auth')

@section('title', 'Sign In | NexSphere Master Hub')

@section('content')
<div class="position-relative min-vh-100 overflow-hidden d-flex align-items-center bg-light">
    <div class="container position-relative z-1 py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-12 col-md-8 col-lg-5 col-xl-4">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-body p-4 p-sm-5">
                        <div class="text-center mb-4">
                            <img src="{{ asset('admin-assets/images/ns.png') }}" alt="NexSphere Logo" class="mb-3" style="height: 56px; max-width: 100%; object-fit: contain;">
                            <h4 class="fw-bold mb-1 text-primary">NexSphere <span class="text-dark">Master Hub</span></h4>
                            <p class="text-muted fs-13 mb-0">SaaS Central Control Plane Console</p>
                        </div>

                        @if (session('status'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li class="fs-13">{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.login.post') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="login" class="form-label fw-semibold fs-13">Username atau Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-start-0 @error('login') is-invalid @enderror"
                                        id="login" name="login" value="{{ old('login') }}"
                                        placeholder="Masukkan username/email" required autofocus>
                                </div>
                                @error('login')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold fs-13">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 @error('password') is-invalid @enderror"
                                        id="password" name="password" placeholder="••••••••" required>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label fs-13 text-muted" for="remember">Ingat Sesi Saya</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                                Masuk ke Hub Console <i class="bi bi-arrow-right-short ms-1 fs-16"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <p class="text-center fs-12 mt-4 text-muted mb-0">&copy; {{ date('Y') }} NexSphere-HRIS Architecture. Secure Central Plane.</p>
            </div>
        </div>
    </div>
</div>
@endsection


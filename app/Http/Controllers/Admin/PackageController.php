<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PackageController extends Controller
{
    public const AVAILABLE_MODULES = [
        'CORE_HR' => 'Core HR (Struktur Organisasi, Jabatan & Karyawan)',
        'ATTENDANCE' => 'Absensi & Jam Kerja (Shift, Geolocation, ADMS)',
        'PAYROLL' => 'Penggajian Otomatis (PPh 21, BPJS, Slip Gaji)',
        'LEAVES' => 'Manajemen Cuti & Izin Online',
        'LOANS' => 'Kasbon / Pinjaman Karyawan',
        'DISCIPLINARY' => 'Surat Peringatan (SP) & Kedisiplinan',
        'OFFBOARDING' => 'Resign, PHK & Surat Pengalaman Kerja',
        'RECRUITMENT' => 'Rekrutmen & Pelamar Kerja (ATS)',
        'PERFORMANCE_KPI' => 'Penilaian Kinerja KPI & OKR',
        'ASSETS' => 'Inventaris & Aset Perusahaan',
    ];

    public function index(): View
    {
        $packages = Package::withCount('subscriptions')->get();

        return view('admin.packages.index', [
            'packages' => $packages,
            'availableModules' => self::AVAILABLE_MODULES,
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.create', [
            'availableModules' => self::AVAILABLE_MODULES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_employees' => ['required', 'integer', 'min:1'],
            'enabled_modules' => ['required', 'array'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active');

        $package = Package::create($validated);

        AuditLog::log('create_package', 'Package', (string) $package->id, "Membuat paket baru {$package->name}");

        return redirect()->route('admin.packages.index')->with('success', "Paket {$package->name} berhasil dibuat!");
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.edit', [
            'package' => $package,
            'availableModules' => self::AVAILABLE_MODULES,
        ]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_employees' => ['required', 'integer', 'min:1'],
            'enabled_modules' => ['required', 'array'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $package->update($validated);

        AuditLog::log('update_package', 'Package', (string) $package->id, "Memperbarui paket {$package->name}");

        return redirect()->route('admin.packages.index')->with('success', "Paket {$package->name} berhasil diperbarui!");
    }
}

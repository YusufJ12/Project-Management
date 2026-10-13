# 🛡️ Panduan Keamanan, Anti-DDoS, dan Setup Cloudflare
## NexSphere Master Hub (Control Plane & Tenant Discovery Authority)

Dokumen ini mencatat arsitektur keamanan, pengaturan Cloudflare, konfigurasi server origin, dan integrasi Laravel untuk **NexSphere Master Hub**.

---

## 1. Arsitektur & Peran Master Hub

Master Hub berfungsi sebagai **otoritas tunggal (*Single Source of Truth*)** untuk:
1. **Aplikasi Mobile (Flutter / Android / iOS)**: Menemukan alamat backend VPS tenant secara dinamis melalui endpoint `GET /api/v1/discovery/company/{code}`.
2. **VPS Node Klien (.NET 10 Worker)**: Mengirimkan heartbeat dan telemetri berkala ke `POST /api/v1/node/heartbeat`.
3. **Admin Web Console**: Dashboard manajemen tenant, brankas kredensial (UU PDP), dan tagihan langganan via `/admin/*`.

```mermaid
flowchart TD
    subgraph Clients ["📱 Klien Luar"]
        M["Mobile App (Flutter)"]
        V["VPS Tenant (.NET Worker)"]
        A["Admin Browser"]
    end

    subgraph CF ["☁️ Cloudflare Global Edge (Anycast CDN & WAF)"]
        CF_DDoS["L3/L4/L7 Anti-DDoS Unmetered"]
        CF_Rule["Security Rule: Bypass /api/*"]
    end

    subgraph Origin ["🖥️ Server Origin Master Hub"]
        Nginx["Web Server (Nginx / OpenResty)"]
        Laravel["Laravel 13 Application\n(TrustProxies + RateLimiter)"]
    end

    M -->|GET /api/v1/discovery/company/{code}| CF_DDoS
    V -->|POST /api/v1/node/heartbeat| CF_DDoS
    A -->|GET /admin/login| CF_DDoS

    CF_DDoS --> CF_Rule
    CF_Rule -->|Pass-Through Fast Path| Nginx
    CF_Rule -->|WAF Protected| Nginx
    Nginx --> Laravel
```

---

## 2. Konfigurasi Cloudflare DNS & Proxy

- **Domain Utama**: `nexsphere.web.id`
- **Subdomain Master Hub**: `masterhub.nexsphere.web.id`
- **Nameservers Cloudflare**:
  - `patrick.ns.cloudflare.com`
  - `rihana.ns.cloudflare.com`

### A. DNS Records di Cloudflare:
| Type | Name | Content / Target | Proxy Status | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| **A** | `masterhub` | `36.50.77.52` *(Origin IP)* | 🟠 **Proxied (Awan Oranye)** | Menyembunyikan IP server asli |
| **A** | `@` (`nexsphere.web.id`) | `36.50.77.52` | 🟠 **Proxied** | Domain utama |
| **CNAME** | `www` | `nexsphere.web.id` | 🟠 **Proxied** | Alias web |
| **CNAME** | `mail` | `nexsphere.web.id` | ⚪ **DNS only (Abu-abu)** | Jalur email server |

> [!IMPORTANT]
> Dengan menyalakan status **Proxied (Awan Oranye)** pada subdomain `masterhub`, IP publik origin server (`36.50.77.52`) tertutup 100% dan digantikan oleh IP Anycast Cloudflare (`104.21.x.x` dan `172.67.x.x`).

### B. Konfigurasi SSL/TLS di Cloudflare:
- **Menu**: `SSL/TLS > Overview`
- **Encryption Mode**: **Full** (atau **Full (strict)**)
- *Catatan Penting*: Jangan memilih mode *Flexible*, karena origin server sudah memiliki sertifikat SSL sendiri. Mode *Flexible* akan menyebabkan redirect loop (*ERR_TOO_MANY_REDIRECTS*).

---

## 3. Cloudflare Security Rules (WAF Bypass untuk Jalur API)

Karena aplikasi mobile dan background worker VPS tidak memiliki mesin JavaScript untuk menyelesaikan teka-teki CAPTCHA / Bot Challenge, jalur `/api/*` harus dikecualikan dari WAF challenge.

- **Menu**: `Security > Security rules > Custom rules > Create rule`
- **Nama Rule**: `Bypass for Mobile and Node API`
- **Kondisi (When incoming requests match)**:
  - **Field**: `URI Path`
  - **Operator**: `starts with`
  - **Value**: `/api/`
- **Tindakan (Then take action)**:
  - **Action**: `Skip`
  - **Komponen yang di-skip**:
    - ☑️ `All remaining custom rules`
    - ☑️ `All managed rules`
    - ☑️ `All Super Bot Fight Mode Rules`
  - *(Catatan: Jangan mencentang "All remaining custom rules in this phase" bersamaan dengan "All remaining custom rules" agar tidak error validasi)*.
- **Status**: `Active`

---

## 4. Penanganan Imunify360 WebShield di Server Origin

### A. Gejala Masalah:
Request API dari mobile app atau curl mengembalikan respon HTML dengan indikator:
- Parameter `?wsidchk=[angka]`
- Teks: *"Please wait while your request is being verified..."*
- Respon JSON: `"message": "Access denied by Imunify360 bot-protection. IPs used for automation should be whitelisted"`

### B. Penyebab:
Fitur **WebShield / Anti-Bot dari Imunify360** di level root shared hosting mendeteksi traffic yang datang dari IP proxy Cloudflare dan otomatis memicu verifikasi browser.

### C. Solusi:
1. Di cPanel: Matikan **ModSecurity** untuk subdomain `masterhub.nexsphere.web.id`.
2. Di Support Hosting (DomaiNesia): Minta tim teknis memasukkan domain ke daftar putih (*whitelist*):
   ```bash
   imunify360-agent whitelist domain add masterhub.nexsphere.web.id
   ```
   Serta menambahkan rentang IP Cloudflare ke *Trusted / Known Proxies*.

---

## 5. Konfigurasi Aplikasi Laravel Master Hub

### A. Trust Cloudflare Proxies (`bootstrap/app.php`)
Agar Laravel dapat membaca **IP asli perangkat HP mobile** dan **IP asli server VPS klien** (bukan IP perantara Cloudflare), tambahkan `trustProxies(at: '*')`:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```
Dengan konfigurasi ini, `request()->ip()` akan secara otomatis membaca header `CF-Connecting-IP` atau `X-Forwarded-For`.

### B. Rate Limiting (`routes/api.php` & `routes/web.php`)
Perlindungan anti-brute-force di level aplikasi:
```php
// routes/api.php
Route::prefix('v1/discovery')->middleware('throttle:60,1')->group(function () {
    Route::get('/company/{companyCode}', [CompanyDiscoveryController::class, 'resolve']);
    Route::get('/domain/{domain}', [CompanyDiscoveryController::class, 'resolveDomain'])->where('domain', '.*');
});

// routes/web.php
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
```

### C. CORS Khusus Mobile & SPA (`config/cors.php`)
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_headers' => ['*'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
```

---

## 6. Prosedur Migrasi Server & Domain Masa Depan

### Skenario 1: Pindah ke VPS Baru (Domain Tetap `masterhub.nexsphere.web.id`)
Karena Cloudflare sudah aktif, proses migrasi sangat cepat dan tanpa downtime:
1. Setup aplikasi Master Hub di VPS baru (install PHP 8.3, Nginx, copy file `.env` dan database).
2. Masuk ke dashboard Cloudflare > menu **DNS**.
3. Edit record `masterhub`, ganti IP `36.50.77.52` dengan **IP VPS baru**.
4. Klik **Save**.
5. *Hasil*: Seluruh aplikasi mobile dan VPS klien otomatis terarah ke server baru dalam hitungan detik tanpa perlu rilis ulang APK Android!

### Skenario 2: Mengganti Nama Domain (Misal ke `nexsphere.id` / `nexsphere.io`)
1. Tambahkan domain baru tersebut ke akun Cloudflare.
2. Di repository aplikasi mobile Flutter: update nilai `_defaultDiscoveryUrl` pada `tenant_discovery_service.dart`.
3. Di VPS klien: update baris `MASTER_HUB_URL` pada file `.env`.
4. Rilis versi APK baru ke pengguna.


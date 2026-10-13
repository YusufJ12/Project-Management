# 🚀 Panduan Deployment DomaiNesia Shared Hosting (cPanel via SSH)
## NexSphere Project Management System (Laravel 13 + Inertia.js + Vue 3)

Panduan operasional ini mendokumentasikan langkah-langkah implementasi dan konfigurasi produksi sistem **NexSphere** pada lingkungan **Shared Hosting DomaiNesia** (berbasis cPanel dengan akses SSH, MultiPHP 8.3, dan Redis), serta jalur transisi tanpa perubahan kode (*Zero-Rewrite*) saat scale-up ke VPS.

---

### 1. 🏗️ Arsitektur Direktori: Isolasi Keamanan Tingkat Tinggi

> [!CRITICAL]
> **JANGAN PERNAH** menaruh seluruh berkas kode Laravel (termasuk folder `.env`, `vendor/`, `app/`) langsung di dalam `public_html`. Hal tersebut mengekspos kredensial database dan logika aplikasi ke publik internet.

Gunakan struktur isolasi berikut di server hosting Anda:

```text
/home/username/
├── projectmanagement/           <-- Root aplikasi Laravel (Di luar akses publik)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env                    <-- Kredensial terlindungi 100%
│   └── artisan
│
└── public_html/                <-- Hanya berkas publik (Web Root)
    ├── index.php               <-- Disesuaikan jalurnya ke projectmanagement
    ├── .htaccess               <-- Rewrite rules Apache
    ├── favicon.ico
    ├── robots.txt
    ├── build/                  <-- Bundle Vite (JS, CSS terkompilasi)
    ├── admin-assets/           <-- Asset tema MasterHub
    └── storage/                <-- Symlink ke /home/username/projectmanagement/storage/app/public
```

---

### 2. 🔑 Langkah Deployment Bertahap via SSH DomaiNesia

#### Langkah 2.1: Login ke Server melalui SSH
Buka terminal lokal Anda dan login ke server DomaiNesia:
```bash
ssh username@nama-domain-anda.com -p 22
```

#### Langkah 2.2: Verifikasi Versi MultiPHP 8.3 CLI
DomaiNesia menggunakan CloudLinux MultiPHP. Pastikan binary PHP 8.3 digunakan pada terminal SSH:
```bash
# Cek lokasi PHP 8.3
/opt/alt/php83/usr/bin/php -v

# Buat alias sementara pada session SSH atau simpan di ~/.bashrc
alias php="/opt/alt/php83/usr/bin/php"
alias composer="/opt/alt/php83/usr/bin/php /opt/cpanel/composer/bin/composer"
```

#### Langkah 2.3: Clone / Upload Repository
```bash
cd ~
git clone https://github.com/akun-anda/ProjectManagement.git projectmanagement
cd projectmanagement
```

#### Langkah 2.4: Salin dan Konfigurasi `.env` Produksi
```bash
cp .env.example .env
nano .env
```
Sesuaikan variabel environment berikut:
```env
APP_NAME=NexSphere
APP_ENV=production
APP_KEY=base64:... (generate dengan: php artisan key:generate)
APP_DEBUG=false
APP_URL=https://nama-domain-anda.com

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=username_nexsphere
DB_USERNAME=username_nexuser
DB_PASSWORD=PasswordDatabaseKuat123!

SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
QUEUE_CONNECTION=database

# Konfigurasi Redis DomaiNesia (Jika diaktifkan di cPanel)
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

FILESYSTEM_DISK=public
```

#### Langkah 2.5: Instalasi Dependensi & Migrasi Database
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

#### Langkah 2.6: Konfigurasi Web Root di `public_html`
Pindahkan konten folder `public/` ke `public_html/` atau sesuaikan `index.php`:
```bash
# Salin aset publik ke public_html
cp -r ~/projectmanagement/public/* ~/public_html/
cp ~/projectmanagement/public/.htaccess ~/public_html/
```

Edit berkas `~/public_html/index.php`:
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Tentukan jalur pemeliharaan
if (file_exists($maintenance = __DIR__.'/../projectmanagement/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Muat Autoloader Composer
require __DIR__.'/../projectmanagement/vendor/autoload.php';

// Bootstrap Framework Laravel
/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../projectmanagement/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

#### Langkah 2.7: Konfigurasi Symlink Penyimpanan Berkas (*Storage Link*)
Di server cPanel, jalankan:
```bash
cd ~/public_html
ln -s /home/username/projectmanagement/storage/app/public storage
```
*Atau jalankan artisan dari direktori aplikasi:*
```bash
cd ~/projectmanagement
php artisan storage:link
```

#### Langkah 2.8: Optimasi Kecepatan Produksi
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### 3. ⏰ Pengaturan Cron Job di cPanel DomaiNesia

Masuk ke **cPanel DomaiNesia $\rightarrow$ Menu Cron Jobs**, lalu tambahkan jadwal perintah per menit (`* * * * *`):

```bash
/opt/alt/php83/usr/bin/php /home/username/projectmanagement/artisan schedule:run >> /dev/null 2>&1
```

Fungsi cron ini akan menjalankan:
1. Pengecekan tenggat waktu tugas yang mendekati deadline (notifikasi otomatis).
2. Pembersihan file lampiran sementara.
3. Pemrosesan antrean *Queue Jobs* secara berkala di latar belakang.

---

### 4. ⚡ Penanganan Antrean (*Queue Worker*) di Shared Hosting

Karena cPanel Shared Hosting umumnya tidak mengizinkan *Supervisor daemon* jangka panjang, antrean database dapat diproses menggunakan scheduler bawaan:

Tambahkan di `app/Console/Kernel.php` atau `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
```

---

### 5. 🌐 Jalur Transisi ke VPS (*Zero-Rewrite Roadmap*)

Aplikasi NexSphere dirancang dengan arsitektur bersih yang siap ditingkatkan (*scale-up*) ke VPS tanpa mengubah kode aplikasi:

| Fitur | Shared Hosting (DomaiNesia) | VPS (Ubuntu 24.04 / Debian) | Keterangan Transisi |
| :--- | :--- | :--- | :--- |
| **Realtime WebSockets** | Pusher Free API / Polling AJAX | **Laravel Reverb** | Cukup ubah `BROADCAST_CONNECTION=reverb` di `.env` |
| **Runtime Server** | Apache / LiteSpeed (MultiPHP 8.3) | **Laravel Octane (FrankenPHP)** | Tingkatkan throughput hingga 10x lipat |
| **Pekerja Antrean** | Cron `queue:work --stop-when-empty` | **Supervisor Daemon** | Proses antrean asinkronus instan (0 delay) |
| **In-Memory Cache** | cPanel Redis Service | **Dedicated Redis / Valkey Server** | Kecepatan latensi sub-milidetik |

---
*Dokumen ini merupakan bagian dari spesifikasi operasional resmi NexSphere Project Management System.*


# 🌐 NexSphere Master Hub
> **Central Control Plane, Tenant Discovery Authority & SaaS Management Platform**  
> Target Production: `https://masterhub.nexsphere.web.id`

---

## 📌 Ringkasan Sistem

**NexSphere Master Hub** adalah pusat kendali dan otoritas penemuan (*Discovery Authority*) tunggal bagi seluruh ekosistem **NexSphere-HRIS** multi-VPS:

1. **Aplikasi Mobile (Single APK/AAB)**: Tidak pernah menyimpan alamat server VPS klien secara statis. Aplikasi mobile meminta resolusi alamat VPS ke Master Hub berdasarkan `company_code`.
2. **Web Admin Tenant**: Mengenali konfigurasi tenant secara otomatis melalui domain/subdomain (`domain_frontend`).
3. **VPS Klien (10+ Node Server Terpisah)**: Secara berkala mengirimkan detak jantung (*Heartbeat & Telemetri*) ke Master Hub via shared secret HMAC (`MASTER_HUB_SECRET`) untuk sinkronisasi lisensi dan pemantauan kesehatan node.

---

## 📚 Dokumentasi Terkait

- 🛡️ **[Panduan Keamanan, Anti-DDoS, dan Setup Cloudflare](CLOUDFLARE_SECURITY_SETUP.md)**: Konfigurasi lengkap Cloudflare DNS, WAF Bypass untuk jalur API mobile, penanganan Imunify360, dan TrustProxies Laravel.
- 📋 **[Roadmap & Rencana Peningkatan Sistem](plan_master_hub_laravel.md)**: Detail fase 1 s/d fase 5 implementasi Master Hub.

---

## 🚀 Fitur Utama

- **Discovery Engine Cepat (< 50ms)**: Dilengkapi caching 10 menit dengan auto-eviction saat data tenant diubah.
- **Otomatisasi Siklus Langganan (Lifecycle Scheduler)**:
  - H-7 sebelum jatuh tempo: Auto-generate invoice perpanjangan (`INV/YYYY/MM/XXXX`).
  - Hari H: Transisi otomatis ke masa tenggang (*Grace Period* 7 hari).
  - H+7: Auto-suspend tenant dan mengunci akses sistem.
- **Monitoring Kesehatan VPS Node Realtime (3-Status)**:
  - 🟢 **Online**: Melapor dalam $\le 5$ menit terakhir dengan RAM & CPU normal ($\le 85\%$).
  - 🟡 **Warning**: Respons 5–15 menit terakhir atau beban RAM/CPU $> 85\%$.
  - 🔴 **Offline**: Tidak melapor lebih dari 15 menit (memicu alert audit).
- **Brankas Kredensial & Audit Trail (Kepatuhan UU PDP)**:
  - Enkripsi AES-256 untuk kredensial root VPS, cPanel, database, dan Cloudflare.
  - Setiap pembukaan password dan generator `.env` dicatat dalam log audit beserta IP dan User-Agent.

---

## 🛠️ Instalasi & Menjalankan Lokal

```bash
# Clone repository
git clone https://github.com/YusufJ12/NexSphere-Master-Hub.git
cd NexSphere-Master-Hub

# Install dependensi
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Migrasi database dan seeder data awal
php artisan migrate --seed

# Menjalankan aplikasi
php artisan serve
```

---

## 🧪 Menjalankan Pengujian (Tests)

```bash
php artisan test
```
*Seluruh fitur Master Hub telah terverifikasi dengan 29+ test cases dan 123+ assertions.*

---

## 📄 Lisensi
Hak Cipta © 2026 NexSphere Teknologi Indonesia.

# Product Requirements Document (PRD)

## NexSphere Project Management (TaskSphere)

| Atribut | Nilai |
|---|---|
| Versi | 1.3 |
| Status | Draft untuk validasi |
| Pemilik produk | Yusuf J. (asumsi, konfirmasi sebelum rilis) |
| Target rilis | MVP v1 untuk shared hosting; pengembangan kapasitas berikutnya di VPS |
| Sasaran kualitas | Skalabel, mudah dirawat, dan andal |
| Arsitektur web | Laravel + Vue 3 + Inertia |
| Dokumen teknis | [PROJECT_PLAN.md](PROJECT_PLAN.md) |
| ERD | [ERD.md](ERD.md) |

> Dokumen ini menetapkan tujuan produk dan perilaku yang harus terlihat oleh pengguna. Pilihan teknis dan urutan pengerjaan dijelaskan di `PROJECT_PLAN.md`.

## 1. Ringkasan dan tujuan

NexSphere adalah platform kerja modular untuk membantu tim merencanakan, mengerjakan, dan memantau pekerjaan. Pengguna dapat mengatur ruang kerja, memilih struktur pekerjaan yang sesuai, lalu melihat pekerjaan melalui tampilan dan alur yang mereka perlukan. Arah jangka panjangnya adalah fleksibilitas platform seperti ClickUp, dengan prioritas fitur ditentukan dari kebutuhan pengguna dan kapasitas operasional.

**Tujuan MVP:** membuktikan fondasi kerja yang aman dan berguna: workspace, Space, List, task, penanggung jawab, tenggat, komentar, dan tampilan Board/List. Model inti harus dapat diperluas ke jenis pekerjaan, field, dan tampilan tambahan tanpa memigrasikan seluruh data pengguna.

**Prinsip produk jangka panjang:**

- Pengguna dapat mengatur hierarki dan pengelompokan pekerjaan, bukan dipaksa ke satu struktur proyek.
- Task adalah unit kerja inti; status, tipe, field, relasi, dan tampilan dapat dikonfigurasi sesuai kebutuhan.
- Fitur lanjutan ditambahkan sebagai modul bertahap dengan izin, audit, performa, dan pengalaman yang konsisten.
- Cakupan “seperti ClickUp” adalah arah produk, bukan klaim paritas fitur atau target satu rilis.
- Pertumbuhan kapasitas dilakukan berdasarkan metrik dan kebutuhan nyata; kompleksitas infrastruktur tidak ditambahkan sebelum ada alasan terukur.

**Indikator keberhasilan MVP (target awal, tinjau setelah pilot):**

- Pengguna dapat membuat workspace, Space/List, dan task tanpa bantuan administrator teknis.
- Anggota dapat menemukan task yang menjadi tanggung jawabnya dan memperbarui statusnya.
- Perubahan status dan komentar tercatat serta terlihat oleh anggota proyek yang berhak.
- Tidak ada akses baca/tulis lintas workspace atau proyek tanpa keanggotaan yang sesuai.
- Tim pilot dapat menyelesaikan alur proyek inti tanpa kehilangan perubahan atau memerlukan reload manual untuk menyimpan.

## 2. Sasaran dan batas produk

### Pengguna sasaran awal

Asumsi yang perlu dikonfirmasi sebelum implementasi: tim internal/agensi kecil, satu atau beberapa workspace, dengan beberapa proyek aktif dan anggota yang berkolaborasi melalui web desktop maupun ponsel.

### Kebutuhan utama

- Project manager perlu melihat pekerjaan, pemilik, tenggat, dan hambatan proyek.
- Kontributor perlu tahu pekerjaan yang harus dilakukan dan cara melaporkan progres.
- Owner perlu mengelola anggota dan memastikan data hanya terlihat oleh pihak yang diundang.

### Di luar MVP, tetapi masuk arah produk bertahap

- Portal klien eksternal dan akses publik.
- Hierarki folder, custom fields, tipe task, template, task berulang, dependensi, dan subtask bertingkat.
- Calendar, timeline/Gantt, workload, dashboard kustom, saved views, sprint, time tracking, goal, dan laporan lanjutan.
- Automasi, API/webhook, integrasi pihak ketiga, impor data massal, dokumen kolaboratif, dan editor rich-text.
- Portal klien, akses publik, aplikasi native, billing, dan resource planning.

Fitur-fitur tersebut bukan komitmen semua akan dibangun. Prioritasnya mengikuti pilot, permintaan pengguna, biaya pemeliharaan, dan batas hosting.

## 3. Persona dan hak akses

Role bersifat kontekstual: keanggotaan workspace memberi akses ke workspace; keanggotaan Space memberi akses ke Space dan List turunannya sesuai izin. Role Space tidak memberikan akses ke workspace lain.

| Aksi | Owner workspace | Admin workspace | Manager proyek | Member proyek | Viewer proyek |
|---|---:|---:|---:|---:|---:|
| Mengubah pengaturan workspace / mengelola admin | Ya | Tidak | Tidak | Tidak | Tidak |
| Mengundang/menghapus anggota workspace | Ya | Ya | Tidak | Tidak | Tidak |
| Membuat/mengarsipkan Space/List | Ya | Ya | Ya, Space yang dikelola | Tidak | Tidak |
| Mengatur anggota dan kolom kerja | Ya | Ya | Ya, Space yang dikelola | Tidak | Tidak |
| Membuat/mengedit task dan menetapkan assignee | Ya | Ya | Ya | Ya, task di Space yang diikuti | Tidak |
| Mengubah status task | Ya | Ya | Ya | Ya, task di Space yang diikuti | Tidak |
| Melihat Space, List, dan task | Semua dalam workspace | Semua dalam workspace | Space yang dikelola | Space yang diikuti | Space yang diikuti |
| Menghapus workspace | Ya, dengan konfirmasi | Tidak | Tidak | Tidak | Tidak |

**Aturan keanggotaan:** anggota workspace belum otomatis menjadi anggota setiap Space. Owner/admin dapat mengelola keanggotaan Space. Akses List dan task mengikuti Space pada MVP. Setiap akses harus diperiksa di server melalui Policy/Gate; menyembunyikan tombol di UI bukan kontrol akses.

## 4. Alur utama pengguna

1. Pengguna mendaftar atau menerima undangan, lalu masuk ke workspace.
2. Owner/admin membuat Space dan List kerja, lalu memilih anggota serta manager Space.
3. Manager menyiapkan kolom kerja dan membuat task berisi judul, deskripsi, prioritas, assignee, dan tenggat.
4. Member melihat My Tasks atau Board/List, memperbarui status, checklist, dan komentar.
5. Tim melihat progres Space/List dan riwayat perubahan.

### Arah struktur kerja

Target struktur bertahap: `Workspace → Space → Folder (opsional) → List → Task`. Space mengelompokkan area kerja besar; Folder mengelompokkan List; List berisi task dan menentukan konteks tampilan Board/List. Untuk MVP, Folder dapat dilewati dan satu Space dapat langsung memiliki beberapa List. Istilah “Proyek” dapat dipakai sebagai nama awal Space atau List di UI, tetapi model data dan API harus memakai satu istilah yang konsisten dan tidak mengunci hierarki hanya pada proyek tunggal.

## 5. Persyaratan fungsional MVP

### FR-01 — Akun dan autentikasi

- Login, logout, lupa/reset kata sandi, verifikasi email bila transport email tersedia.
- Batasi percobaan login dan gunakan sesi aman.
- Pengguna dapat mengubah nama, avatar, kata sandi, dan preferensi tema.
- Registrasi terbuka dapat dinonaktifkan; pembuatan workspace pertama diberikan kepada pengguna yang mendaftar.

### FR-02 — Workspace dan undangan

- Pengguna dapat membuat dan memilih workspace yang diikutinya.
- Owner/admin mengundang anggota melalui email dengan masa berlaku undangan yang dapat dikonfigurasi.
- Undangan memiliki status pending/accepted/expired/revoked; token tidak disimpan sebagai teks terbuka.
- Admin tidak dapat menghapus atau menurunkan role owner terakhir.

### FR-03 — Ruang kerja, proyek, dan anggota (MVP)

- MVP menyediakan Space dan List kerja: nama, deskripsi, kode/slug unik dalam konteksnya, status, tanggal mulai dan tenggat opsional. Folder bersifat opsional dan dapat ditunda.
- Status proyek: `planning`, `active`, `on_hold`, `completed`, `archived`.
- Akses diwariskan dari workspace/space/list berdasarkan aturan yang ditetapkan; role spesifik proyek dipertahankan untuk kompatibilitas MVP, tetapi rancangan izin harus dapat berkembang ke scope container.
- Progress dihitung dari task yang berada di kolom bertanda `is_done`; task yang diarsipkan dikecualikan.

### FR-04 — Task dan Kanban

- Kolom default: `Backlog`, `To Do`, `In Progress`, `Review`, `Done`; manager dapat menambah, mengubah nama/urutan, dan mengarsipkan kolom.
- Kolom `Done` ditandai dengan `is_done`; setidaknya satu kolom selesai harus tersedia. Kolom yang berisi task tidak dapat dihapus sebelum task dipindahkan.
- Task memiliki judul wajib, deskripsi teks, prioritas (`low`, `medium`, `high`, `urgent`), tanggal tenggat opsional, pembuat, kolom, urutan, dan satu atau lebih assignee proyek.
- Drag-and-drop mengirim perubahan kolom dan urutan ke server. UI melakukan optimistic update; jika gagal, UI mengembalikan posisi sebelumnya dan memberi pesan yang dapat dipahami.
- Update posisi divalidasi bahwa task, kolom tujuan, dan pengguna berada dalam proyek yang sama. Update berkelompok harus atomik dan aman terhadap posisi ganda.

### FR-05 — Detail task dan kolaborasi

- Task dapat memiliki checklist berurutan; item checklist dapat ditambah, diedit, diurutkan, dan diselesaikan.
- Anggota proyek dapat menambahkan komentar; komentar menampilkan penulis dan waktu.
- Lampiran diizinkan hanya untuk tipe dan ukuran yang dikonfigurasi; unduh melalui endpoint berotorisasi.
- Penghapusan task menggunakan arsip/soft delete agar aktivitas dan referensi tidak hilang tanpa sengaja.

### FR-06 — Notifikasi dan aktivitas

- MVP menyediakan notifikasi dalam aplikasi untuk penugasan task dan komentar baru pada task yang pernah dikomentari atau ditugaskan kepada pengguna.
- Notifikasi disimpan agar tetap tersedia ketika pengguna offline; pengguna dapat menandainya sudah dibaca.
- Activity log mencatat pembuatan/perubahan/arsip proyek dan task, perubahan status/assignee, komentar, dan lampiran. Log tidak dapat diedit oleh pengguna biasa.
- Broadcast real-time adalah peningkatan UX; kegagalan koneksi real-time tidak boleh menggagalkan penyimpanan utama atau notifikasi yang tersimpan.

### FR-07 — Daftar, pencarian, dan dashboard

- My Tasks dan daftar proyek mendukung pencarian, filter status/prioritas/assignee/tenggat, pengurutan, dan pagination server-side.
- MVP menyediakan Kanban dan daftar task yang responsif. Bulk edit, DataTables, dan quick search global masuk rilis berikutnya kecuali pilot membuktikan perlu.
- Dashboard menampilkan proyek yang boleh diakses, task aktif, task selesai, dan task terlambat. Definisi overdue: tenggat sebelum tanggal lokal hari ini dan task belum berada pada kolom selesai.

### FR-08 — API aplikasi dan kesiapan Flutter

- Aplikasi web menggunakan Vue 3 + Inertia dengan Laravel sebagai server untuk routing, validasi, sesi, dan otorisasi. Blade dipakai sebagai root shell Inertia atau untuk halaman sederhana, bukan sebagai layer utama halaman aplikasi.
- Inertia bukan REST API dan tidak dipakai Flutter. Inertia menghubungkan route/controller Laravel ke halaman Vue; aplikasi mobile memakai API terpisah.
- Backend menyediakan REST API berversi (`/api/v1`) untuk alur inti yang juga dibutuhkan klien Flutter: autentikasi, profil, workspace, Space/List, task, assignee, checklist, komentar, dan notifikasi.
- API dan web memakai aturan domain, validasi, dan Policy yang sama. API tidak boleh memiliki jalur akses yang lebih longgar daripada web.
- API mengembalikan JSON Resource dengan format error, pagination, timestamp UTC, dan filter yang konsisten. Perubahan kontrak yang memutus kompatibilitas memerlukan versi API baru atau masa transisi yang didokumentasikan.
- API autentikasi mobile menggunakan Laravel Sanctum personal access token melalui Bearer token; pengguna dapat melihat dan mencabut token/perangkat. Token disimpan di secure storage pada perangkat dan tidak pernah dicatat ke log.
- Token abilities hanya menjadi pembatas tambahan; Policy dan keanggotaan workspace tetap menjadi otorisasi utama.
- Dokumentasi kontrak API (OpenAPI atau format setara) diperbarui bersama endpoint. UI Vue/Inertia tidak perlu memanggil API publik untuk setiap navigasi; web dan API tetap menggunakan domain service dan Policy yang sama.
- Flutter bukan bagian MVP; endpoint dan kontrak inti disiapkan sejak awal agar aplikasi mobile tidak memerlukan akses langsung ke database atau logika bisnis duplikat.

### Persyaratan evolusi platform (pasca-MVP)

- **Struktur:** Folder opsional, pemindahan item dalam hierarki, dan aturan pewarisan izin.
- **Kustomisasi task:** tipe task, custom fields bertipe (teks, angka, tanggal, pilihan, checkbox, user), template, task berulang, relasi/dependensi, dan subtask bertingkat.
- **Views:** Calendar dan Timeline terlebih dahulu; saved views menyimpan filter, sort, grouping, dan kolom tanpa menggandakan task.
- **Perencanaan:** milestone, sprint, time tracking, workload, goal, dan dashboard widget sesuai kebutuhan pilot.
- **Ekosistem:** pencarian global, API/webhook, automasi berbasis trigger-action, dokumen, integrasi, dan kontrol organisasi.

Custom fields dan automasi memerlukan validasi izin, audit, pencarian, ekspor, dan strategi migrasi sebelum dirilis.

## 6. Kriteria penerimaan alur inti

- Pengguna tanpa akses ke Space menerima respons 403/404 saat mengakses URL/API Space, List, atau task secara langsung.
- Membuat/mengubah task, memindahkan task, mengomentari, dan mengunggah lampiran memvalidasi role dan kepemilikan proyek di server.
- Permintaan web dan API untuk operasi yang sama menghasilkan aturan validasi dan keputusan otorisasi yang sama.
- Jika penyimpanan perpindahan Kanban gagal, kartu kembali ke posisi asal dan pesan kesalahan tampil.
- Task tidak dapat dipindahkan ke kolom dari proyek lain, walaupun ID valid.
- Status selesai ditentukan oleh `is_done`, bukan pencocokan nama kolom seperti “Done”.
- Perhitungan dashboard dan progress konsisten dengan filter akses pengguna dan aturan task terarsip.
- Setiap lampiran hanya dapat diunduh oleh anggota proyek berwenang; nama file pengguna tidak dipakai sebagai path penyimpanan.

## 7. Persyaratan non-fungsional

- **Keamanan:** validasi input, CSRF untuk sesi web, otorisasi server-side, proteksi mass assignment, escaping output, rate limit, dan validasi konten/ukuran file. Rahasia tidak disimpan di repository.
- **Skalabilitas:** semua query dibatasi workspace dan izin pengguna; daftar menggunakan pagination server-side; filter utama memiliki indeks; pekerjaan lambat berjalan melalui queue. Target awal p95 ≤ 500 ms untuk halaman/API daftar pada dataset pilot (hingga 10.000 task per workspace, tanpa unduhan file), diukur pada staging dengan cache dan spesifikasi server dicatat. Target 120 ms dihapus sampai ada pengukuran yang mendukungnya.
- **Isolasi data:** workspace adalah batas tenant/data. Semua operasi baca/tulis, pencarian, ekspor, notifikasi, cache key, dan file harus tetap berada dalam scope workspace dan policy yang sah.
- **Ketersediaan:** target pilot awal ≥99% ketersediaan bulanan, tidak termasuk pemeliharaan terjadwal yang diumumkan. Kegagalan Pusher/worker tidak boleh kehilangan data utama; retry, log kegagalan, dan mekanisme pemrosesan ulang harus tersedia.
- **Pemulihan:** target awal RPO ≤24 jam dan RTO ≤8 jam untuk MVP. Backup database dan berkas harus terpisah dari server aplikasi dan pemulihan harus didemonstrasikan sebelum produksi; target ditinjau setelah paket hosting diketahui.
- **Maintainability:** modul memiliki batas tanggung jawab yang jelas; aturan domain dan otorisasi tidak ditempatkan di Vue component atau controller; perubahan schema menggunakan migration; vendor template tidak dimodifikasi langsung bila dapat dioverride melalui layout/komponen aplikasi.
- **Operabilitas:** error aplikasi, failed jobs, kapasitas disk, dan hasil backup dapat diperiksa. Log tidak menyimpan rahasia atau isi sensitif.
- **Aksesibilitas/responsif:** fungsi inti dapat digunakan pada layar ponsel; status tidak disampaikan hanya lewat warna; kontrol utama dapat diakses dengan keyboard.
- **Data:** semua waktu disimpan dalam UTC dan ditampilkan menurut timezone pengguna/workspace; default `Asia/Jakarta` dapat dikonfigurasi.
- **Backup/pemulihan:** backup database dan berkas memiliki jadwal, retensi, lokasi terpisah, serta prosedur restore yang diuji sebelum rilis produksi.
- **Privasi:** tetapkan kebijakan retensi akun, komentar, aktivitas, dan lampiran; penghapusan akun harus menangani data terkait sesuai keputusan produk.

## 8. Ukuran keberhasilan pilot

Catat baseline dan ukur pada 2–4 minggu pilot:

- Persentase pengguna undangan yang aktif membuat atau memperbarui task.
- Jumlah proyek yang menyelesaikan alur backlog → selesai.
- Tingkat kegagalan penyimpanan perubahan dan error otorisasi yang tidak diharapkan.
- Waktu p95 halaman/API daftar pada lingkungan yang dicatat.
- Ketersediaan, kegagalan queue, hasil backup, dan durasi pemulihan saat latihan restore.
- Masukan kualitatif dari project manager dan kontributor tentang hambatan alur kerja.

Target numerik adopsi ditetapkan setelah ukuran dan durasi pilot disepakati; hindari mengarang baseline sebelum data tersedia.

## 9. Rilis

- **MVP — fondasi kerja:** akun, workspace, Space/List, anggota, task/Kanban dan List view, My Tasks, checklist, komentar, notifikasi tersimpan dasar, activity log, filter, otorisasi, API v1 untuk alur inti, backup, dan deployment.
- **Tahap 2 — fleksibilitas dasar:** Folder opsional, custom fields dasar, tipe task/template, saved views, Calendar, pencarian global, dan bulk edit.
- **Tahap 3 — perencanaan tim:** Timeline/Gantt, dependensi, recurring task, milestone, sprint, time tracking, workload, goal, dan dashboard kustom.
- **Tahap 4 — platform dan ekosistem:** automasi, API/webhook, integrasi, dokumen kolaboratif, izin tingkat lanjut, portal klien, serta skala VPS/realtime sesuai kebutuhan.

Urutan setelah MVP adalah hipotesis roadmap, bukan janji tanggal atau paritas ClickUp. Tinjau setiap tahap dari data pilot dan kapasitas tim.

## 10. Keputusan yang perlu divalidasi sebelum implementasi

1. Apakah registrasi terbuka atau hanya melalui undangan setelah workspace pertama dibuat?
2. Apakah owner/admin workspace otomatis boleh melihat seluruh Space dalam workspace? PRD ini mengasumsikan ya; perubahan tetap dibatasi oleh hak aksi masing-masing role.
3. Apakah satu task boleh memiliki beberapa assignee? PRD ini mengasumsikan ya.
4. Batas ukuran/tipe lampiran, retensi, dan kebutuhan backup yang sesuai paket hosting.
5. Dukungan PHP/Laravel, worker queue, Redis, cron, kapasitas disk, dan proses deploy yang benar-benar tersedia di paket hosting.
6. Cakupan dan kontrak endpoint API v1 yang harus tersedia sebelum pengembangan klien Flutter dimulai.

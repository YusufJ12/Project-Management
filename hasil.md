# 📋 Log Hasil Pengembangan: NexSphere Project Management

Dokumen ini mencatat seluruh progres eksekusi pengembangan per fase secara terstruktur, memverifikasi kriteria selesai (*Definition of Done*), serta merekam perubahan file dan hasil pengujian sistem.

* **Repository:** NexSphere Project Management System
* **Basis Teknologi:** Laravel 13.x (PHP 8.3) + MasterHub Admin Template (Bootstrap 5, Tailwind CSS v4, Dark/Light Theme)
* **Target Infrastruktur:** Shared Hosting (DomaiNesia via SSH) $\rightarrow$ VPS (Scale-up)
* **Rencana Mobile (Flutter):** Diparkir untuk tahap akhir setelah Web MVP stabil dan terverifikasi.

---

## 📊 Matriks Status Pelaksanaan Fase

| Fase | Nama Fase | Status | Tanggal Selesai | Verifikasi |
| :---: | :--- | :---: | :---: | :---: |
| **0** | **Validasi, Blueprint Arsitektur, PRD & ERD** | ✅ **SELESAI** | 14 Okt 2026 | Dokumen disetujui, spesifikasi selaras |
| **1** | **Fondasi Codebase, Layout MasterHub & Autentikasi** | ✅ **SELESAI** | 14 Okt 2026 | 7 Tests lulus, Vite build sukses 100% |
| **2** | **Migrasi Database & Model Eloquent (Sesuai ERD)** | ✅ **SELESAI** | 14 Okt 2026 | 11 Tests lulus, Seeder aktif, 0 error |
| **3** | **Workspace, Space & List Management** | ✅ **SELESAI** | 14 Okt 2026 | 19 Tests lulus, UI Switcher aktif, Vite build 100% |
| **4** | **Kanban Board (SortableJS) & Task Engine** | ✅ **SELESAI** | 14 Okt 2026 | 5 Tests lulus, SortableJS aktif, Drag-Drop mulus |
| **5** | **Task Detail (Checklist, Upload File, Komentar, Audit)** | ✅ **SELESAI** | 14 Okt 2026 | 6 Tests lulus, Modal & Subtask aktif, Storage aman |
| **6** | **Dashboard, List View, Notifikasi & Kesiapan Shared Hosting** | ✅ **SELESAI** | 14 Okt 2026 | 6 Tests lulus, List View & Bell aktif, Runbook siap |
| **7** | **API Sanitization & Integrasi Mobile (Flutter)** | ✅ **SELESAI** | 14 Okt 2026 | 8 Tests lulus, Sanctum API aktif, Client Blueprint siap |

---

## 📝 Catatan Rinci Hasil Eksekusi per Fase

### ✅ Fase 0: Validasi, Blueprint Arsitektur, PRD & ERD
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. Analisis lingkungan hosting DomaiNesia (tersedia SSH, Redis, MultiPHP 8.3, Cron Jobs).
  2. Perumusan arsitektur transisi: Shared Hosting (Pusher Free + MySQL) $\rightarrow$ VPS (Laravel Reverb + Octane) dengan *zero-rewrite* kodingan.
  3. Pembuatan dan penyelarasan dokumen spesifikasi:
     - 📄 **[PRD.md](PRD.md):** User personas, role matrix (Owner, Manager, Member, Viewer), sitemap, wireframe visual (Dashboard, Kanban, Task Modal), functional & non-functional requirements.
     - 📊 **[ERD.md](ERD.md):** Skema data hierarki bergaya ClickUp (`Workspace → Space → (Folder) → List → Task`), relasi assignees, checklists, attachments, comments, notifications, activity logs, serta tabel `personal_access_tokens` (Sanctum).
     - 🚀 **[PROJECT_PLAN.md](PROJECT_PLAN.md):** Kontrak teknis, integrasi template MasterHub, aturan otorisasi/policy, dan roadmap fase pengerjaan.
  4. Penyusunan strategi untuk mobile Flutter (diputuskan dikerjakan pada tahap akhir setelah web stabil).
* **Deliverables:**
  - `PRD.md`
  - `ERD.md`
  - `PROJECT_PLAN.md`
  - `hasil.md` (ledger ini)
* **Kriteria Selesai (DoD):** Seluruh dokumen telah selaras, tidak ada kontradiksi entitas data, dan lingkungan siap dieksekusi.

---

### ✅ Fase 1: Fondasi Codebase, Layout MasterHub & Autentikasi
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Inisialisasi Codebase:** Skeleton Laravel 13 (`Laravel Framework 13.34.0`, PHP 8.3) aktif di root proyek.
  2. **Pemasangan Paket:**
     - Backend: `inertiajs/inertia-laravel v3.5.1`.
     - Frontend: `@inertiajs/vue3`, `vue v3.5.x`, `@vitejs/plugin-vue`, `@tailwindcss/vite`, `lucide-vue-next`.
  3. **Konfigurasi Build Pipeline:**
     - `vite.config.js` dikonfigurasi dengan plugin Vue 3 dan path alias `@` ke `/resources/js`.
     - Kompilasi `npm run build` sukses 100% menghasilkan bundle aset di `public/build/assets/`.
  4. **Adaptasi MasterHub UI Theme:**
     - Aset `public/admin-assets/` (Bootstrap 5, FontAwesome, SweetAlert2, SimpleBar) terpasang.
     - Root template `resources/views/app.blade.php` dibuat dengan inisialisasi dark/light theme tanpa *flicker*.
     - Middleware `HandleInertiaRequests.php` didaftarkan di `bootstrap/app.php` dengan data share auth & flash session.
  5. **Komponen Layout & View Vue 3:**
     - `resources/js/Layouts/AuthenticatedLayout.vue`: Sidebar modular, Topbar dengan profil pengguna, toggle Dark/Light mode, dan notifikasi flash toast.
     - `resources/js/Layouts/GuestLayout.vue`: Container bersih dengan logo NexSphere.
     - `resources/js/Pages/Auth/Login.vue`: Form login dengan validasi error dan link registrasi.
     - `resources/js/Pages/Auth/Register.vue`: Form pendaftaran akun baru (otomatis onboarding workspace awal).
     - `resources/js/Pages/Dashboard.vue`: Metrik ringkasan, daftar proyek aktif, dan progress bar.
  6. **Routing & Controller:**
     - `App\Http\Controllers\Auth\AuthController.php`: Alur login, register, logout berbasis Inertia.
     - `App\Http\Controllers\DashboardController.php`: Controller dashboard.
     - `routes/web.php`: Route terdaftar dengan proteksi middleware guest & auth.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseOneInertiaAuthTest.php`: **7/7 tests passed (35 assertions)**.
* **Kriteria Selesai (DoD):** Skeleton terpasang, aset terkompilasi, autentikasi bekerja penuh via Inertia Vue 3, dan seluruh unit test hijau.

---

### ✅ Fase 2: Migrasi Database & Model Eloquent (Sesuai ERD.md)
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Pembersihan Skema Lama:** Menghapus seluruh tabel migrasi legacy tenant/HRIS.
  2. **Pembuatan 6 File Migrasi Relasional:**
     - `2026_10_14_000001_create_workspaces_tables.php`: `workspaces`, `workspace_members`, `workspace_invitations`.
     - `2026_10_14_000002_create_spaces_and_folders_tables.php`: `spaces`, `space_members`, `folders`.
     - `2026_10_14_000003_create_lists_and_stages_tables.php`: `lists`, `task_stages`.
     - `2026_10_14_000004_create_tasks_tables.php`: `tasks`, `task_assignees`, `task_checklists`.
     - `2026_10_14_000005_create_task_collaborations_tables.php`: `task_comments`, `task_attachments`.
     - `2026_10_14_000006_create_notifications_and_activity_logs_tables.php`: `notifications`, `activity_logs`.
  3. **Pembuatan Model Eloquent:**
     - `User`, `Workspace`, `WorkspaceInvitation`, `Space`, `Folder`, `TaskList`, `TaskStage`, `Task`, `TaskChecklist`, `TaskComment`, `TaskAttachment`, `Notification`, `ActivityLog`.
  4. **Database Seeder Realistis:**
     - `DatabaseSeeder.php` mengisi pengguna, workspace, space, list, 5 stage Kanban, 4 sample task, checklist, dan activity log.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseTwoDatabaseModelTest.php`: **4/4 tests passed (16 assertions)**.
* **Kriteria Selesai (DoD):** Skema database selaras [ERD.md](ERD.md), foreign key terproteksi, relasi model aktif, dan seeder berhasil dieksekusi.

---

### ✅ Fase 3: Workspace, Space & List Management
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Controller & Logika Bisnis:**
     - `WorkspaceController.php`: Index ruang kerja, validasi pembuatan workspace baru (slug unik otomatis), dan fungsionalitas switch active workspace via session (`active_workspace_id`).
     - `SpaceController.php`: Index ruang proyek di workspace aktif, perhitungan progress bar persentase dinamis, pembuatan Space baru (otomatis assign role `manager`, membuat default `TaskList`, dan membuat default 5 `TaskStage`s), show detail Space dengan tab navigation, update, dan soft-archive.
     - `DashboardController.php`: Diperbarui untuk menghitung metrik statistik langsung dari workspace aktif di database.
  2. **Global Shared Context (HandleInertiaRequests):**
     - Membagikan `auth.workspaces` dan `auth.currentWorkspace` (beserta sub-daftar space-nya) ke seluruh komponen frontend Vue 3.
  3. **Tampilan Antarmuka Vue 3:**
     - `resources/js/Pages/Workspaces/Index.vue`: Manajemen workspace, badge aktif, modal buat workspace baru.
     - `resources/js/Pages/Spaces/Index.vue`: Daftar Space, status badge, progress bar, deadline, modal tambah space.
     - `resources/js/Pages/Spaces/Show.vue`: Detail Space dengan 3 Tab (Tab Kanban columns, Tab Anggota Tim, Tab Pengaturan).
     - `resources/js/Layouts/AuthenticatedLayout.vue`: Terintegrasi Workspace Switcher dropdown di Header navbar dan sub-menu Space dinamis di Sidebar kiri.
  4. **Build Assets:**
     - Kompilasi `npm run build` sukses 100% tanpa error dalam 4.05 detik.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseThreeWorkspaceSpaceTest.php`: **8/8 tests passed (49 assertions)**.
    - `test_user_can_view_workspaces_index` (PASSED)
    - `test_user_can_create_a_workspace` (PASSED)
    - `test_user_can_switch_active_workspace` (PASSED)
    - `test_user_can_view_spaces_index_for_active_workspace` (PASSED)
    - `test_user_can_create_a_space_with_default_list_and_stages` (PASSED)
    - `test_user_can_view_space_detail_page` (PASSED)
    - `test_unauthorized_user_cannot_view_space_of_another_workspace` (PASSED)
    - `test_user_can_update_space_details` (PASSED)
  - **Total Uji Gabungan (Fase 1 + 2 + 3): 19/19 tests passed (100 assertions) in 1.06s** 🟢.
* **Kriteria Selesai (DoD):** Pengguna dapat membuat & beralih workspace, membuat & mengelola Space/List dengan tahapan Kanban bawaan, dan terisolasi aman antar-organisasi.

---

### ✅ Fase 4: Kanban Board (SortableJS) & Task Engine
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Integrasi Library Drag-and-Drop:**
     - Pemasangan `sortablejs` via npm.
     - Penerapan SortableJS pada setiap kontainer kolom kanban dengan sinkronisasi state Vue 3, ghost class yang elegan, animasi 180ms, dan drag handle.
  2. **Controller & Task Engine Backend (`TaskController.php`):**
     - `store`: Quick Task creation di list dan stage manapun dengan validasi isolasi workspace, penetapan posisi otomatis di urutan terakhir, dan audit log otomatis.
     - `move`: Endpoint `POST /tasks/{task}/move` dengan transaksi basis data atomik (`DB::transaction`). Mendukung perpindahan antar kolom stage maupun penyusunan ulang (*reordering*) di dalam stage yang sama, kalkulasi ulang `position` tetangga, serta pencatatan audit log `activity_logs`.
     - `destroy`: Menghapus task secara aman dengan verifikasi hak akses workspace.
  3. **Antarmuka Kanban Interaktif (`Spaces/Show.vue`):**
     - Kolom Kanban berbasis Bootstrap 5 cards & badges yang responsif.
     - Kartu task menampilkan judul, badge prioritas berwarna (urgent, high, medium, low), indikator checklist (`selesai / total`), deadline waktu, jumlah komentar, dan avatar inisial anggota penanggung jawab.
     - Fitur *Quick Add Task* langsung di bawah masing-masing kolom stage (input inline dengan tombol Simpan & Batal).
     - Tombol opsi aksi kartu (hapus task).
  4. **Build Assets:**
     - Kompilasi `npm run build` sukses 100% tanpa error dalam 3.50 detik.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseFourKanbanEngineTest.php`: **5/5 tests passed (13 assertions)**.
    - `test_user_can_create_a_task_via_quick_add` (PASSED)
    - `test_user_can_move_task_to_another_stage` (PASSED)
    - `test_user_can_reorder_task_within_the_same_stage` (PASSED)
    - `test_user_can_delete_a_task` (PASSED)
    - `test_unauthorized_user_cannot_move_or_delete_task` (PASSED)
  - **Total Uji Keseluruhan Aplikasi (Fase 1 + 2 + 3 + 4 + ExampleTest): 26/26 tests passed (115 assertions) in 1.15s** 🟢.
* **Kriteria Selesai (DoD):** Kartu task dapat dipindah-pindahkan secara drag-and-drop antar kolom kanban, posisi tersimpan presisi di database, quick task langsung berfungsi, dan seluruh test suite hijau.

---

### ✅ Fase 5: Task Detail (Checklist, Upload File, Komentar, Audit)
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Modal / Drawer Task Detail Interaktif (`Show.vue`):**
     - Membuka modal detail task bergaya ClickUp saat kartu task diklik atau melalui menu opsi.
     - Layout dua kolom: Kolom kiri (Deskripsi task, sub-tab rincian checklist & diskusi) dan kolom kanan (Atribut stage, prioritas, tenggat waktu, estimasi menit, dan penanggung jawab/assignees).
     - Sinkronisasi instan data ke kartu Kanban saat modal ditutup atau diedit.
  2. **Sub-sistem Checklist Subtask (`TaskChecklistController.php`):**
     - Endpoint `POST /tasks/{task}/checklists`: Tambah item checklist baru dengan penetapan posisi otomatis.
     - Endpoint `POST /tasks/{task}/checklists/{checklist}/toggle`: Centang status selesai/belum secara dinamis, mencatat ID penyelesai dan waktu selesai.
     - Endpoint `DELETE /tasks/{task}/checklists/{checklist}`: Hapus item checklist.
     - Indikator bilah kemajuan (*progress bar*) persentase penyelesaian subtask langsung di modal dan kartu kanban.
  3. **Sub-sistem Lampiran Berkas / Attachments (`TaskAttachmentController.php`):**
     - Endpoint `POST /tasks/{task}/attachments`: Upload berkas ke Laravel Storage (`public` disk, aman untuk shared hosting cPanel).
     - Validasi tipe file, ukuran hingga 20 MB, dan kalkulasi checksum MD5.
     - Endpoint `GET /tasks/{task}/attachments/{attachment}/download`: Unduh berkas terlampir secara terproteksi.
     - Endpoint `DELETE /tasks/{task}/attachments/{attachment}`: Hapus berkas dari disk dan database.
  4. **Sub-sistem Diskusi & Komentar Tim (`TaskCommentController.php`):**
     - Endpoint `POST /tasks/{task}/comments`: Mengirim komentar diskusi tim.
     - Endpoint `DELETE /tasks/{task}/comments/{comment}`: Menghapus komentar dengan otorisasi (hanya pembuat komentar atau manager/owner workspace).
  5. **Audit Trail & Activity Log Stream:**
     - Menampilkan stream aktivitas kronologis untuk setiap perubahan task (perubahan stage, perubahan prioritas, checklist baru, penyelesaian checklist, upload berkas, komentar baru).
  6. **Build Assets:**
     - Kompilasi `npm run build` sukses 100% tanpa error dalam 3.26 detik.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseFiveTaskDetailTest.php`: **6/6 tests passed (44 assertions)**.
    - `test_user_can_view_task_details_via_json` (PASSED)
    - `test_user_can_update_task_details_and_logs_activity` (PASSED)
    - `test_user_can_add_toggle_and_delete_checklists` (PASSED)
    - `test_user_can_add_and_delete_comments` (PASSED)
    - `test_user_can_upload_download_and_delete_attachments` (PASSED)
    - `test_unauthorized_user_cannot_access_or_modify_task` (PASSED)
  - **Total Uji Keseluruhan Aplikasi (Fase 1 + 2 + 3 + 4 + 5 + ExampleTest): 32/32 tests passed (159 assertions) in 1.43s** 🟢.
* **Kriteria Selesai (DoD):** Modal task detail berfungsi penuh, checklist dapat dicentang dengan progress bar dinamis, upload & download berkas berjalan aman di storage abstraction, komentar dan audit trail tercatat rapi, dan seluruh test suite hijau.

---

### ✅ Fase 6: Dashboard Metrik, List View, Notifikasi & Kesiapan Shared Hosting
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Tampilan Alternatif List View / Table View (`Spaces/Show.vue`):**
     - Tab baru **"Tampilan Tabel (List View)"** berdampingan langsung dengan Papan Kanban.
     - Toolbar filter interaktif: Pencarian teks (*search by title/description* secara real-time), filter dropdown prioritas (All, Urgent, High, Medium, Low), dan filter tahapan stage.
     - Data table responsif bergaya DataTables menampilkan judul tugas, badge tahapan berwarna, badge prioritas, avatar penanggung jawab, progress bar checklist subtask, jumlah lampiran & komentar diskusi, dan tanggal tenggat.
     - Klik pada baris tabel membuka modal detail task yang sama persis secara interaktif.
  2. **Peningkatan Dashboard Analitik (`DashboardController.php` & `Dashboard.vue`):**
     - Metrik statistik akurat langsung dari basis data: Total Spaces, Rasio Penyelesaian Tugas (% Completion Rate), Jumlah Task Prioritas Tinggi & Urgent, serta Task Overdue (terlambat).
     - Widget **"Tugas yang Ditugaskan ke Saya"** (`myTasks`): Menampilkan daftar tugas spesifik user login di workspace aktif dengan link navigasi langsung.
     - Widget **"Aktivitas Tim Terkini"** (`recentActivities`): Stream audit aktivitas anggota tim dengan badge aksi dan timestamp relatif (*time ago*).
  3. **Pusat Notifikasi Aplikasi / In-App Notification Hub (`NotificationController.php` & `AuthenticatedLayout.vue`):**
     - Ikon lonceng notifikasi di Topbar Header dengan badge indikator jumlah belum dibaca (*Unread Count*).
     - Dropdown menu notifikasi: Menampilkan ringkasan pesan, pembaruan tugas, tombol *Tandai Telah Dibaca*, dan tombol *Tandai Semua Dibaca*.
     - Endpoint API: `GET /notifications`, `POST /notifications/{notification}/read`, `POST /notifications/read-all`.
  4. **Panduan Operasional Deployment Shared Hosting (`SHARED_HOSTING_GUIDE.md`):**
     - Panduan teknis lengkap khusus **Shared Hosting DomaiNesia** (cPanel via SSH):
       1. Isolasi keamanan direktori: Memisahkan root kode aplikasi di luar `public_html/`.
       2. Pengaturan MultiPHP 8.3 CLI binary (`/opt/alt/php83/usr/bin/php`).
       3. Variabel `.env` produksi (Redis session & cache, MySQL, error logging).
       4. Setup symlink storage (`php artisan storage:link`).
       5. Perintah optimasi Laravel (`config:cache`, `route:cache`, `view:cache`).
       6. Konfigurasi Cron Job Scheduler cPanel per menit (`schedule:run`).
       7. Eksekusi antrean (*Queue Worker*) via cron tanpa daemon supervisor.
       8. *Zero-Rewrite Roadmap* menuju VPS (Laravel Reverb & Laravel Octane).
  5. **Build Assets:**
     - Kompilasi `npm run build` sukses 100% tanpa error dalam 3.57 detik.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseSixDashboardAndListTest.php`: **6/6 tests passed (46 assertions)**.
    - `test_user_can_view_dashboard_with_calculated_metrics_and_my_tasks` (PASSED)
    - `test_user_can_fetch_notifications_via_api` (PASSED)
    - `test_user_can_mark_notification_as_read` (PASSED)
    - `test_user_can_mark_all_notifications_as_read` (PASSED)
    - `test_user_cannot_mark_other_users_notification_as_read` (PASSED)
    - `test_user_can_render_space_show_page_for_list_view` (PASSED)
  - **Total Uji Keseluruhan Aplikasi (Fase 1 + 2 + 3 + 4 + 5 + 6 + ExampleTest): 38/38 tests passed (205 assertions) in 1.52s** 🟢.
* **Kriteria Selesai (DoD):** List view tabel berfungsi dengan pencarian & filter, dashboard menampilkan metrik riil dan tugas penugasan, notifikasi lonceng aktif di header, panduan hosting DomaiNesia tersedia, dan seluruh test suite hijau.

---

### ✅ Fase 7: API Sanitization & Integrasi Mobile (Flutter)
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan:**
  1. **Standardisasi RESTful API Mobile (Laravel Sanctum):**
     - Membangun 15 endpoint API terstruktur di bawah prefix `/api/v1/`:
       - `POST /api/v1/auth/login`: Autentikasi mobile & penerbitan Bearer token Sanctum.
       - `POST /api/v1/auth/register`: Pendaftaran pengguna baru & pembuatan workspace otomatis.
       - `GET /api/v1/auth/me`: Informasi profil pengguna terautentikasi dan daftar workspace aktif.
       - `POST /api/v1/auth/logout`: Pencabutan (*revocation*) token akses secara aman.
       - `GET /api/v1/workspaces`: Daftar ruang kerja pengguna beserta jumlah proyek & anggota.
       - `GET /api/v1/workspaces/{workspace}/spaces`: Daftar proyek di dalam ruang kerja.
       - `GET /api/v1/spaces/{space}`: Detail lengkap proyek, daftar tahapan Kanban, dan seluruh task.
       - `POST /api/v1/tasks`: Pembuatan task baru dari aplikasi mobile.
       - `GET /api/v1/tasks/{task}`: Detail task lengkap (assignee, subtask checklist, komentar).
       - `PUT /api/v1/tasks/{task}`: Pembaruan data task (prioritas, tenggat, assignees).
       - `POST /api/v1/tasks/{task}/move`: Sinkronisasi perpindahan kolom Kanban & pengurutan posisi kartu.
       - `DELETE /api/v1/tasks/{task}`: Penghapusan task.
       - `POST /api/v1/tasks/{task}/checklists`: Penambahan subtask checklist baru.
       - `POST /api/v1/tasks/{task}/checklists/{checklist}/toggle`: Centang status selesai/belum subtask.
       - `POST /api/v1/tasks/{task}/comments`: Pengiriman komentar diskusi task.
  2. **API Resource Transformers (`app/Http/Resources/Api/...`):**
     - Dibuat 7 class transformer untuk menyeragamkan respon JSON: `UserResource`, `WorkspaceResource`, `SpaceResource`, `TaskResource`, `TaskStageResource`, `TaskChecklistResource`, dan `TaskCommentResource`.
  3. **Arsitektur & Blueprint Klien Mobile Flutter (`mobile/`):**
     - Setup berkas konfigurasi `mobile/pubspec.yaml` (Dio, BLoC, Secure Storage, Intl).
     - Lapisan Jaringan (`mobile/lib/core/network/api_client.dart`): Klien Dio dengan interceptor otomatis penyisipan header `Authorization: Bearer <token>` dari Secure Storage dan penanganan error 401.
     - Konstanta Rute (`mobile/lib/core/constants/api_endpoints.dart`): Manajemen URL endpoint (mendukung Android Emulator `10.0.2.2:8000`, iOS Simulator `localhost:8000`, dan URL server produksi).
     - Entitas Model Data (`UserModel`, `TaskModel`, `StageModel`).
     - Repository Kanban (`mobile/lib/features/kanban/repositories/kanban_repository.dart`): Integrasi API untuk pengambilan data papan, penambahan task, pemindahan kolom, dan toggle checklist.
     - Dokumentasi Operasional (`mobile/README.md`): Panduan lengkap menjalankan aplikasi di emulator, integrasi ke backend web, dan build APK release.
  4. **Build Assets:**
     - Kompilasi `npm run build` sukses 100% tanpa error dalam 3.29 detik.
* **Hasil Pengujian & Verifikasi:**
  - `PhaseSevenMobileApiTest.php`: **8/8 tests passed (54 assertions)**.
    - `test_user_can_login_via_api_and_receive_sanctum_token` (PASSED)
    - `test_user_can_register_via_api` (PASSED)
    - `test_user_can_get_profile_and_workspaces_via_auth_me` (PASSED)
    - `test_user_can_get_spaces_and_tasks_for_kanban_mobile` (PASSED)
    - `test_user_can_create_and_move_task_via_api` (PASSED)
    - `test_user_can_toggle_checklist_and_add_comment_via_api` (PASSED)
    - `test_user_can_logout_and_revoke_token` (PASSED)
    - `test_unauthenticated_request_is_rejected` (PASSED)
  - **Total Uji Keseluruhan Aplikasi (Fase 1 + 2 + 3 + 4 + 5 + 6 + 7 + ExampleTest): 46/46 tests passed (259 assertions) in 1.89s** 🟢.
* **Kriteria Selesai (DoD):** Seluruh endpoint API mobile siap dan terproteksi Sanctum, respon JSON konsisten via Resource Transformers, struktur klien Flutter lengkap dengan Dio Interceptor & Repository, dan seluruh unit test hijau 100%.

---

---

### 🎨 Polish Antarmuka: Sidebar Minimize/Maximize & MasterHub Dark/Light Mode Toggle
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Perbaikan & Refinement:**
  1. **Sidebar Minimize / Maximize (`#toggleSidebar`):**
     - Memperbaiki selector atribut dari `data-sidebar-size="sm"` menjadi standar MasterHub `data-sidebar="icon"` dan `data-sidebar="default"` pada elemen root `<html>`.
     - Mengadaptasi seluruh styling MasterHub dari template referensi (`master.blade.php`):
       - Mode Maksimal: Lebar sidebar 260px dengan offset `.app-header` dan `.app-wrapper` yang selaras secara dinamis.
       - Mode Minimal (Icon Dock): Lebar sidebar 75px, brand text dan menu title disembunyikan secara rapi, icon terpusat di tombol 44px, dan tooltip navigasi melayang (*floating tooltip pop*) aktif saat kursor di-hover pada menu icon.
       - Ikon tombol berganti dinamis antara `bi-arrow-bar-left` (saat maksimal) dan `bi-arrow-bar-right` (saat diminimalkan).
       - Mode Mobile (< 992px): Bekerja sebagai *off-canvas drawer* dengan backdrop blur (`#sidebar-backdrop`), otomatis menutup saat backdrop disentuh atau menu diklik.
     - Persistensi status tersimpan di `localStorage.getItem('data-sidebar')` dan dipulihkan sebelum paint untuk mencegah FOUC.
  2. **Toggle Dark Mode & Light Mode (`#toggleMode`):**
     - Menghilangkan tombol custom kotak/biru lama dan menggantikannya dengan desain kapsul/pill bawaan MasterHub:
       - Kontainer kapsul `.dark-mode-btn` di header dengan background adaptif `var(--pe-body-bg)`.
       - Sepasang tombol bulat 38px: `#lightModeBtn` (`bi-brightness-high`) dan `#darkModeBtn` (`bi-moon-stars`).
       - Indikator aktif `.active` dengan efek elevasi `var(--pe-secondary-bg)` dan warna aksen `var(--pe-primary)`.
       - Klik pada kontainer atau tombol langsung mengalihkan mode tema (`light` / `dark`), menerapkan `data-bs-theme` ke elemen root, dan menyimpannya di `localStorage`.
  3. **Penyederhanaan Navigasi Profil:**
     - Menghapus item menu **Profil Saya** (`/profile`) dari navigasi sidebar agar sidebar fokus pada alur kerja proyek & tugas.
     - Memastikan aksi **Edit Profil** tersedia secara eksklusif dan rapi pada menu dropdown profil pengguna di header atas.
  4. **Penerapan Border Card pada Header Seluruh Halaman:**
     - Membungkus seluruh judul/header halaman dalam kontainer card terstruktur (`card border border-secondary-subtle shadow-sm rounded-3 mb-4`) agar rapi, berjarak pas, dan selaras dengan layout MasterHub:
       - [`Dashboard.vue`](resources/js/Pages/Dashboard.vue): Judul metrik, deskripsi, dan tombol aksi (*Semua Spaces* & *Buka Proyek*).
       - [`Spaces/Index.vue`](resources/js/Pages/Spaces/Index.vue): Judul ruang kerja, badge nama workspace, dan tombol *Buat Space Baru*.
       - [`Workspaces/Index.vue`](resources/js/Pages/Workspaces/Index.vue): Judul manajemen workspace dan tombol *Buat Workspace Baru*.
       - [`Profile/Edit.vue`](resources/js/Pages/Profile/Edit.vue): Halaman edit profil lengkap dengan form update nama, email, dan ganti password.
       - Dibuat komponen terpusat [`PageHeader.vue`](resources/js/Components/PageHeader.vue) untuk konsistensi di masa mendatang.
  5. **Verifikasi:**
     - `npm run build`: Kompilasi sukses 100% (3.19 detik).
     - `php artisan test`: 49/49 tests passed (280 assertions, 100% PASS).

---

### 🛡️ Aktivasi Modul: My Tasks, Activity Logs & Super Admin RBAC
* **Status:** SELESAI (*DONE*)
* **Tanggal:** 14 Oktober 2026
* **Cakupan Pekerjaan & Fitur:**
  1. **Modul Tugas Saya (My Tasks - `/my-tasks`):**
     - Controller: [`MyTaskController.php`](app/Http/Controllers/MyTaskController.php) mengambil seluruh tugas yang ditugaskan (*assigned*) ke pengguna aktif.
     - Tampilan: [`MyTasks/Index.vue`](resources/js/Pages/MyTasks/Index.vue) lengkap dengan 4 kartu metrik (Total Tugas, Sedang Berjalan, Sudah Selesai, Terlambat/Overdue), filter status, filter prioritas, filter tenggat waktu, dan pencarian cepat secara *debounce*.
     - Navigasi satu klik langsung membuka papan Kanban proyek terkait.
  2. **Modul Riwayat Aktivitas (Activity Logs - `/activity-logs`):**
     - Controller: [`ActivityLogController.php`](app/Http/Controllers/ActivityLogController.php) menyajikan jejak audit transparan dari aktivitas tim dalam workspace aktif.
     - Model: [`ActivityLog.php`](app/Models/ActivityLog.php) dilengkapi *accessor* cerdas `description` yang otomatis memformat teks aksi dalam Bahasa Indonesia.
     - Tampilan: [`ActivityLogs/Index.vue`](resources/js/Pages/ActivityLogs/Index.vue) dengan visual timeline stream, ikon aksi berwarna, link space, filter jenis aksi, dan pagination.
  3. **Manajemen Pengguna & Super Admin RBAC (`/users`):**
     - Migrasi: `2026_10_14_000007_add_role_to_users_table.php` menambahkan kolom `role` (`super_admin`, `admin`, `member`) pada tabel `users`.
     - Model User: Ditambahkan helper `isSuperAdmin()`, `isAdmin()`, dan `hasWorkspaceRole()`.
     - Controller: [`UserController.php`](app/Http/Controllers/UserController.php) dengan proteksi *authorization gate* (hanya Super Admin / Admin yang dapat mengakses).
     - Fitur RBAC:
       - Penetapan/perubahan peran pengguna secara langsung (`super_admin`, `admin`, `member`) dengan proteksi pencegahan *demote* pada Super Admin terakhir.
       - Pendaftaran pengguna baru langsung dari panel admin dan pengaitan ke workspace awal.
       - Penghapusan akun pengguna dengan proteksi akun diri sendiri.
     - Tampilan: [`Users/Index.vue`](resources/js/Pages/Users/Index.vue) dengan matriks kartu metrik (Total, Super Admin, Admin, Member), badge peran bergaya MasterHub, dan dropdown manajemen peran.
     - Menu Sidebar: Ditampilkan bagian **"Administrasi & RBAC"** (`/users`) khusus untuk pengguna dengan hak akses admin/super admin.
  4. **Verifikasi:**
     - `npm run build`: Kompilasi sukses 100% (3.09 detik).
     - `php artisan test`: **57/57 tests passed (341 assertions, 100% PASS)** via `MyTasksAndRbacTest.php`.

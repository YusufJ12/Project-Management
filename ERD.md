# ERD — NexSphere Project Management

Versi 1.0 · Selaras dengan [PRD.md](PRD.md) dan [PROJECT_PLAN.md](PROJECT_PLAN.md)

Diagram ini menggambarkan fondasi data untuk MVP dan jalur perluasan. **MVP** menggunakan Workspace → Space → List → Task; Folder bisa dibuat dalam skema tetapi UI-nya ditunda. Bagian ekstensi adalah rancangan untuk tahap berikutnya, bukan scope implementasi MVP.

## 1. ERD fondasi MVP

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string avatar_path
        string timezone
        string theme
        timestamp created_at
        timestamp updated_at
    }

    PERSONAL_ACCESS_TOKENS {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string name
        string token UK
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
    }

    WORKSPACES {
        bigint id PK
        bigint owner_id FK
        string name
        string slug UK
        timestamp created_at
        timestamp updated_at
    }

    WORKSPACE_MEMBERS {
        bigint workspace_id PK
        bigint user_id PK
        string role
        timestamp joined_at
    }

    WORKSPACE_INVITATIONS {
        bigint id PK
        bigint workspace_id FK
        bigint invited_by FK
        string email
        string role
        string token_hash UK
        timestamp expires_at
        timestamp accepted_at
        timestamp revoked_at
        timestamp created_at
    }

    SPACES {
        bigint id PK
        bigint workspace_id FK
        string name
        string slug
        text description
        string status
        date starts_at
        date due_at
        timestamp archived_at
        timestamp created_at
        timestamp updated_at
    }

    SPACE_MEMBERS {
        bigint space_id PK
        bigint user_id PK
        string role
        timestamp joined_at
    }

    FOLDERS {
        bigint id PK
        bigint space_id FK
        bigint parent_folder_id FK
        string name
        int position
        timestamp archived_at
        timestamp created_at
        timestamp updated_at
    }

    LISTS {
        bigint id PK
        bigint space_id FK
        bigint folder_id FK
        string name
        string slug
        string status
        int position
        timestamp archived_at
        timestamp created_at
        timestamp updated_at
    }

    TASK_STAGES {
        bigint id PK
        bigint list_id FK
        string name
        string color
        boolean is_done
        int position
        timestamp archived_at
    }

    TASKS {
        bigint id PK
        bigint list_id FK
        bigint stage_id FK
        bigint created_by FK
        string title
        text description
        string priority
        date due_at
        int estimate_minutes
        int position
        timestamp archived_at
        timestamp created_at
        timestamp updated_at
    }

    TASK_ASSIGNEES {
        bigint task_id PK
        bigint user_id PK
        timestamp assigned_at
    }

    TASK_CHECKLISTS {
        bigint id PK
        bigint task_id FK
        bigint completed_by FK
        string title
        boolean is_completed
        int position
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
    }

    TASK_COMMENTS {
        bigint id PK
        bigint task_id FK
        bigint user_id FK
        text content
        timestamp edited_at
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    TASK_ATTACHMENTS {
        bigint id PK
        bigint task_id FK
        bigint uploaded_by FK
        string original_name
        string storage_path UK
        bigint size_bytes
        string detected_mime
        string checksum
        timestamp created_at
    }

    NOTIFICATIONS {
        bigint id PK
        bigint user_id FK
        bigint workspace_id FK
        string type
        json data
        timestamp read_at
        timestamp created_at
    }

    ACTIVITY_LOGS {
        bigint id PK
        bigint workspace_id FK
        bigint space_id FK
        bigint list_id FK
        bigint actor_id FK
        string action
        string subject_type
        bigint subject_id
        json metadata
        timestamp created_at
    }

    USERS ||--o{ WORKSPACES : owns
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : authenticates
    USERS ||--o{ WORKSPACE_MEMBERS : joins
    WORKSPACES ||--o{ WORKSPACE_MEMBERS : has
    WORKSPACES ||--o{ WORKSPACE_INVITATIONS : invites
    USERS ||--o{ WORKSPACE_INVITATIONS : sends
    WORKSPACES ||--o{ SPACES : contains
    USERS ||--o{ SPACE_MEMBERS : joins
    SPACES ||--o{ SPACE_MEMBERS : has
    SPACES ||--o{ FOLDERS : groups
    FOLDERS o|--o{ FOLDERS : nests
    SPACES ||--o{ LISTS : contains
    FOLDERS o|--o{ LISTS : groups
    LISTS ||--|{ TASK_STAGES : defines
    LISTS ||--o{ TASKS : contains
    TASK_STAGES ||--o{ TASKS : holds
    USERS ||--o{ TASKS : creates
    TASKS ||--o{ TASK_ASSIGNEES : assigned
    USERS ||--o{ TASK_ASSIGNEES : receives
    TASKS ||--o{ TASK_CHECKLISTS : has
    USERS o|--o{ TASK_CHECKLISTS : completes
    TASKS ||--o{ TASK_COMMENTS : has
    USERS ||--o{ TASK_COMMENTS : writes
    TASKS ||--o{ TASK_ATTACHMENTS : includes
    USERS ||--o{ TASK_ATTACHMENTS : uploads
    USERS ||--o{ NOTIFICATIONS : receives
    WORKSPACES ||--o{ NOTIFICATIONS : scopes
    WORKSPACES ||--o{ ACTIVITY_LOGS : records
    SPACES o|--o{ ACTIVITY_LOGS : scopes
    LISTS o|--o{ ACTIVITY_LOGS : scopes
    USERS o|--o{ ACTIVITY_LOGS : acts
```

## 2. Aturan integritas dan akses

- Seluruh tabel data milik pelanggan diturunkan ke `workspace_id` secara langsung atau lewat relasi yang tervalidasi. Policy harus memeriksa tenant scope pada setiap operasi; ID yang valid tidak berarti pengguna berhak mengaksesnya.
- `workspaces.owner_id` harus menunjuk user yang menjadi anggota workspace dengan role `owner`. Transfer owner dilakukan dalam transaksi; workspace tidak boleh tanpa owner.
- Unique constraint yang disarankan: `(workspace_id,user_id)` di `workspace_members`; `(space_id,user_id)` di `space_members`; `(workspace_id,slug)` di `spaces`; `(space_id,slug)` untuk `lists`; `(task_id,user_id)` untuk `task_assignees`.
- `lists.folder_id` boleh `NULL` untuk list langsung di Space. Bila terisi, Folder harus berada dalam `space_id` yang sama. `folders.parent_folder_id` harus menunjuk folder pada Space yang sama dan tidak boleh membentuk siklus.
- `tasks.stage_id` harus menunjuk stage milik `tasks.list_id`. Assignee harus menjadi anggota Space yang menaungi List. Pindah task antar-List memerlukan validasi akses dan stage tujuan.
- Setiap List memiliki minimal satu stage bertanda `is_done`; penghapusan/arsip stage yang masih berisi task harus dicegah atau didahului pemindahan task secara atomik.
- `notifications.workspace_id` memudahkan pembatasan tenant dan penghapusan/retensi data. Kolom `data` tidak boleh menjadi tempat menyimpan rahasia.
- `personal_access_tokens` mengikuti tabel Sanctum; `tokenable_type/id` adalah relasi morph. Nilai token yang tersimpan harus berupa hash sesuai implementasi Sanctum, bukan token mentah.
- `activity_logs.subject_type/subject_id` adalah relasi polimorfik untuk objek yang dicatat, sehingga tidak memiliki foreign key langsung. Pertahankan `workspace_id` dan scope terkait untuk otorisasi serta retensi.
- File attachment disimpan privat dengan path acak; nama file asli hanya metadata. Unduh selalu melalui endpoint yang memeriksa akses task.
- Gunakan `archived_at` untuk arsip yang dapat dipulihkan. Kebijakan hard delete dan retensi komentar, aktivitas, serta file ditentukan terpisah.

> Beberapa aturan lintas tabel (misalnya stage dan task harus dalam List yang sama) mungkin tidak dapat dijamin dengan foreign key sederhana. Validasi domain dan operasi transaksional tetap wajib; constraint database ditambahkan bila engine target mendukungnya dengan aman.

## 3. Ekstensi data pasca-MVP

Entitas ini ditambahkan saat fitur terkait diprioritaskan. Rancangan menyimpan referensi ke task yang sama, bukan menggandakan task per tampilan.

```mermaid
erDiagram
    WORKSPACES ||--o{ TASK_TYPES : defines
    TASK_TYPES ||--o{ TASKS : classifies
    WORKSPACES ||--o{ CUSTOM_FIELD_DEFINITIONS : configures
    SPACES o|--o{ CUSTOM_FIELD_DEFINITIONS : scopes
    LISTS o|--o{ CUSTOM_FIELD_DEFINITIONS : scopes
    CUSTOM_FIELD_DEFINITIONS ||--o{ TASK_CUSTOM_FIELD_VALUES : defines
    TASKS ||--o{ TASK_CUSTOM_FIELD_VALUES : stores
    TASKS ||--o{ TASK_RELATIONS : source
    TASKS ||--o{ TASK_RELATIONS : target
    USERS ||--o{ SAVED_VIEWS : owns
    WORKSPACES o|--o{ SAVED_VIEWS : scopes
    SPACES o|--o{ SAVED_VIEWS : scopes
    LISTS o|--o{ SAVED_VIEWS : scopes
    TASKS ||--o{ TIME_ENTRIES : tracks
    USERS ||--o{ TIME_ENTRIES : logs
    WORKSPACES ||--o{ AUTOMATIONS : configures
    AUTOMATIONS ||--o{ AUTOMATION_RUNS : executes
```

Kolom konsep yang perlu diputuskan saat implementasi:

- `task_types`: `workspace_id`, nama, konfigurasi, status aktif.
- `custom_field_definitions`: scope (`workspace_id`, `space_id`, atau `list_id`), nama, key, tipe, aturan validasi, urutan, dan status aktif. Terapkan aturan agar scope tepat satu tingkat yang diizinkan.
- `task_custom_field_values`: unique `(task_id, custom_field_definition_id)`; gunakan kolom nilai bertipe (teks, angka, tanggal, boolean, JSON terbatas) sesuai tipe field dan kebutuhan filter. Jangan gunakan JSON bebas untuk semua nilai tanpa indeks dan validasi.
- `task_relations`: `source_task_id`, `target_task_id`, `relation_type`; cegah self-link dan siklus untuk jenis dependency.
- `saved_views`: owner, scope, view type, filter, sort, grouping, dan kolom yang terlihat sebagai konfigurasi tervalidasi.
- `time_entries`: task, user, waktu mulai/selesai atau durasi, deskripsi opsional; perubahan dan retensinya dapat diaudit.
- `automations` dan `automation_runs`: definisi trigger/action berversi, status, idempotency key, hasil, error, dan retry. Jangan mengeksekusi kode bebas dari pengguna.

## 4. Keputusan sebelum migration pertama

1. Pilih satu format ID untuk semua tabel (big integer atau UUID/ULID); jangan mencampur tanpa alasan.
2. Tetapkan collation dan strategi pencarian yang cocok untuk MySQL/MariaDB hosting target.
3. Putuskan apakah Folder dibuat di database MVP meski belum ada UI. Rekomendasi: buat relasi `folder_id` nullable pada List dan entitas Folder sederhana agar penambahan Folder tidak memerlukan migrasi ulang struktur List.
4. Putuskan strategi soft delete, retensi, backup, timezone, dan batas ukuran lampiran.
5. Tinjau foreign key, unique index, cascade behavior, dan urutan migration terhadap engine database yang benar-benar dipakai.

# 📱 NexSphere Mobile Client (Flutter)

Aplikasi mobile resmi **NexSphere Project Management** untuk platform Android & iOS, dibangun menggunakan Flutter dengan arsitektur **Clean Architecture (BLoC & Repository Pattern)** yang terintegrasi secara *real-time* ke backend Laravel 13 melalui RESTful API Laravel Sanctum.

---

### 📂 Struktur Arsitektur Folder Flutter

```text
mobile/
├── lib/
│   ├── core/
│   │   ├── constants/
│   │   │   └── api_endpoints.dart      <-- Konfigurasi Base URL & rute endpoint
│   │   ├── network/
│   │   │   └── api_client.dart         <-- Dio HTTP client + Sanctum Bearer Interceptor
│   │   └── theme/
│   │       └── app_theme.dart          <-- Tema gelap & terang selaras MasterHub
│   ├── features/
│   │   ├── auth/
│   │   │   ├── models/user_model.dart  <-- Model user
│   │   │   ├── repositories/           <-- Auth repository (Login, Register, Logout)
│   │   │   └── bloc/                   <-- State management autentikasi
│   │   └── kanban/
│   │       ├── models/
│   │       │   ├── task_model.dart     <-- Model data task, prioritas, checklist
│   │       │   └── stage_model.dart    <-- Model tahapan kolom Kanban
│   │       ├── repositories/
│   │       │   └── kanban_repository.dart <-- Interaksi API Kanban (Get, Move, Create)
│   │       └── presentation/           <-- Layar Papan Kanban & Modal Task
│   └── main.dart
├── pubspec.yaml
└── README.md
```

---

### 🌐 Konfigurasi Alamat API (`api_endpoints.dart`)

Ubah konstanta `baseUrl` di `lib/core/constants/api_endpoints.dart` sesuai target perangkat uji coba:

| Target Pengujian | Format Base URL | Keterangan |
| :--- | :--- | :--- |
| **Android Emulator** | `http://10.0.2.2:8000/api/v1` | Alamat loopback emulator Android ke host lokal |
| **iOS Simulator** | `http://localhost:8000/api/v1` | Simulator Mac berbagi localhost dengan backend |
| **HP Fisik (WiFi Lokal)** | `http://192.168.1.xxx:8000/api/v1` | Pastikan laptop & HP berada di jaringan WiFi sama |
| **Production Server** | `https://nama-domain-anda.com/api/v1` | Server DomaiNesia Shared Hosting / VPS |

---

### 🚀 Cara Menjalankan Aplikasi Mobile

1. **Pastikan Backend Laravel Berjalan:**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Masuk ke Direktori Mobile & Ambil Dependensi:**
   ```bash
   cd mobile
   flutter pub get
   ```

3. **Jalankan Aplikasi di Emulator / Perangkat:**
   ```bash
   flutter run
   ```

4. **Kompilasi APK Produksi (Android):**
   ```bash
   flutter build apk --release
   ```
   *File APK siap pasang akan tersedia di: `build/app/outputs/flutter-apk/app-release.apk`.*

---

### 🔒 Fitur Keamanan Integrasi API
1. **Bearer Token Storage:** Token autentikasi disimpan di *Keystore (Android)* dan *Keychain (iOS)* menggunakan library `flutter_secure_storage`.
2. **Auto-Inject Authorization:** Setiap request HTTP secara otomatis disematkan header `Authorization: Bearer <token>` oleh Dio Interceptor.
3. **Penyelarasan Drag-and-Drop:** Perubahan posisi task pada papan mobile disinkronkan secara atomik via endpoint `POST /api/v1/tasks/{task}/move`.


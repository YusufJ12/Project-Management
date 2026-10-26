# NexSphere Mobile Client

Flutter client for NexSphere Project Management. It communicates with the Laravel REST API under `/api/v1` using Dio and Sanctum bearer tokens. The current app is an early client implementation; this document describes the files present and does not claim feature parity with the web app, push-based realtime, or a complete BLoC architecture.

## Current code layout

```text
lib/
├── main.dart                         # Main app and current screen flows
├── core/
│   ├── constants/api_endpoints.dart  # API base URL and endpoint paths
│   └── network/api_client.dart       # Dio client and secure token storage
└── features/
    ├── auth/models/user_model.dart
    └── kanban/
        ├── models/
        └── repositories/kanban_repository.dart
```

`flutter_bloc` is a declared dependency, but the presence of the package alone does not mean all features use BLoC. Check the actual feature code before relying on that architecture.

## Requirements

- Flutter SDK compatible with the Dart constraint in `pubspec.yaml` (`>=3.3.0 <4.0.0`).
- A reachable NexSphere API server and a valid API base URL.

## Configure the API URL

The current default in `lib/core/constants/api_endpoints.dart` points to the NexSphere production domain. For local development, change `ApiEndpoints.baseUrl` to the appropriate URL, including `/api/v1`:

| Target | Example base URL |
|---|---|
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| iOS simulator | `http://127.0.0.1:8000/api/v1` |
| Physical device on local Wi-Fi | `http://<computer-lan-ip>:8000/api/v1` |
| Production | `https://<your-domain>/api/v1` |

Use HTTPS for production. Do not commit private endpoints, credentials, or tokens. If the base URL is moved to build-time configuration in the future, update this guide with the actual mechanism.

## Run locally

From the `mobile` directory:

```sh
flutter pub get
flutter run
```

The Laravel backend can be started locally from the repository root with:

```sh
php artisan serve --host=0.0.0.0 --port=8000
```

For a release APK:

```sh
flutter build apk --release
```

## Authentication and synchronization

- The Dio client reads the Sanctum token from `flutter_secure_storage` and sends it as a bearer token.
- Logout should revoke the current API token and clear its local secure-storage value; verify both sides when changing authentication behavior.
- The client uses HTTP requests and manual refresh flows. It does not currently provide WebSocket push synchronization.
- The API and mobile feature coverage may differ from the web app. Verify endpoint availability and authorization before adding a screen.

import 'dart:async';
import 'dart:io' show Platform;
import 'package:flutter/foundation.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import '../constants/api_endpoints.dart';
import '../network/api_client.dart';

/// Top-level background message handler required by Firebase Messaging
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

/// Structured notification payload received from Firebase Cloud Messaging (FCM)
class FcmNotificationPayload {
  final int? taskId;
  final String? spaceSlug;
  final int? spaceId;
  final String? type;
  final String title;
  final String message;
  final Map<String, dynamic> data;

  const FcmNotificationPayload({
    this.taskId,
    this.spaceSlug,
    this.spaceId,
    this.type,
    required this.title,
    required this.message,
    this.data = const {},
  });

  /// Factory constructor to parse raw FCM message data map
  factory FcmNotificationPayload.fromMap(Map<String, dynamic> map) {
    // Determine data dictionary
    final Map<String, dynamic> dataMap;
    if (map['data'] is Map<String, dynamic>) {
      dataMap = map['data'] as Map<String, dynamic>;
    } else if (map['data'] is Map) {
      dataMap = Map<String, dynamic>.from(map['data'] as Map);
    } else {
      dataMap = map;
    }

    // Determine notification dictionary if present
    final Map<String, dynamic> notifMap;
    if (map['notification'] is Map<String, dynamic>) {
      notifMap = map['notification'] as Map<String, dynamic>;
    } else if (map['notification'] is Map) {
      notifMap = Map<String, dynamic>.from(map['notification'] as Map);
    } else {
      notifMap = const {};
    }

    // Title resolution
    final title = notifMap['title']?.toString() ??
        dataMap['title']?.toString() ??
        map['title']?.toString() ??
        '';

    // Message / body resolution
    final message = notifMap['body']?.toString() ??
        dataMap['message']?.toString() ??
        dataMap['body']?.toString() ??
        map['message']?.toString() ??
        map['body']?.toString() ??
        '';

    // Safe integer parser helper
    int? parseInt(dynamic value) {
      if (value == null) return null;
      if (value is int) return value;
      return int.tryParse(value.toString());
    }

    final taskId = parseInt(dataMap['task_id'] ?? dataMap['taskId'] ?? map['task_id']);
    final spaceId = parseInt(dataMap['space_id'] ?? dataMap['spaceId'] ?? map['space_id']);
    final spaceSlug = dataMap['space_slug']?.toString() ??
        dataMap['spaceSlug']?.toString() ??
        map['space_slug']?.toString();
    final type = dataMap['type']?.toString() ?? map['type']?.toString();

    return FcmNotificationPayload(
      taskId: taskId,
      spaceSlug: spaceSlug,
      spaceId: spaceId,
      type: type,
      title: title,
      message: message,
      data: dataMap,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'task_id': taskId,
      'space_slug': spaceSlug,
      'space_id': spaceId,
      'type': type,
      'title': title,
      'message': message,
      'data': data,
    };
  }

  @override
  String toString() =>
      'FcmNotificationPayload(taskId: $taskId, spaceSlug: $spaceSlug, spaceId: $spaceId, type: $type, title: $title, message: $message)';
}

/// Service to manage background FCM push notifications and device tokens
class FcmService {
  static final FcmService _instance = FcmService._internal();
  factory FcmService() => _instance;
  FcmService._internal();

  bool _isInitialized = false;
  bool _isMockMode = false;
  String? _currentToken;

  final StreamController<FcmNotificationPayload> _onMessageController =
      StreamController<FcmNotificationPayload>.broadcast();
  final StreamController<FcmNotificationPayload> _onOpenedAppController =
      StreamController<FcmNotificationPayload>.broadcast();

  bool get isInitialized => _isInitialized;
  bool get isMockMode => _isMockMode;
  String? get currentToken => _currentToken;

  /// Stream of notifications received while app is in foreground
  Stream<FcmNotificationPayload> get onMessageStream =>
      _onMessageController.stream;

  /// Stream of notifications tapped by the user to open the app
  Stream<FcmNotificationPayload> get onOpenedAppStream =>
      _onOpenedAppController.stream;

  /// Alias for onOpenedAppStream
  Stream<FcmNotificationPayload> get onNotificationTappedStream =>
      _onOpenedAppController.stream;

  /// Initialize the FCM service.
  /// When in [mockMode] (or in test/mock environments), runs purely in memory without crashing.
  Future<void> initialize({
    bool mockMode = false,
    String? initialToken,
    ApiClient? apiClient,
  }) async {
    _isMockMode = mockMode;
    if (initialToken != null) {
      _currentToken = initialToken;
    }
    _isInitialized = true;

    if (!mockMode) {
      try {
        if (Firebase.apps.isEmpty) {
          await Firebase.initializeApp();
        }

        FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

        final messaging = FirebaseMessaging.instance;
        final settings = await messaging.requestPermission(
          alert: true,
          badge: true,
          sound: true,
          provisional: false,
        );
        debugPrint('[FCM] Permission status: ${settings.authorizationStatus}');

        // ignore: deprecated_member_use
        final token = await messaging.getToken();
        debugPrint('[FCM] Token retrieved: $token');
        if (token != null) {
          _currentToken = token;
          if (apiClient != null) {
            final regSuccess = await registerDeviceToken(apiClient, token);
            debugPrint('[FCM] registerDeviceToken result: $regSuccess');
          }
        }

        // ignore: deprecated_member_use
        messaging.onTokenRefresh.listen((newToken) {
          debugPrint('[FCM] Token refreshed: $newToken');
          _currentToken = newToken;
          if (apiClient != null) {
            registerDeviceToken(apiClient, newToken);
          }
        });

        FirebaseMessaging.onMessage.listen((RemoteMessage message) {
          debugPrint('[FCM] Foreground message received: ${message.messageId}');
          handleIncomingMessage({
            'notification': {
              'title': message.notification?.title,
              'body': message.notification?.body,
            },
            'data': message.data,
          });
        });

        FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
          debugPrint('[FCM] Notification tapped in background: ${message.messageId}');
          handleNotificationTapped({
            'notification': {
              'title': message.notification?.title,
              'body': message.notification?.body,
            },
            'data': message.data,
          });
        });

        final initialMessage = await messaging.getInitialMessage();
        if (initialMessage != null) {
          debugPrint('[FCM] Initial message on launch: ${initialMessage.messageId}');
          handleNotificationTapped({
            'notification': {
              'title': initialMessage.notification?.title,
              'body': initialMessage.notification?.body,
            },
            'data': initialMessage.data,
          });
        }
      } catch (e, stack) {
        debugPrint('[FCM] Initialization warning/error: $e\n$stack');
      }
    }
  }

  /// Detect platform device type for backend registration
  String get _deviceType {
    if (kIsWeb) return 'web';
    if (Platform.isIOS) return 'ios';
    return 'android';
  }

  /// Register FCM device token to NexSphere backend
  Future<bool> registerDeviceToken(
    ApiClient apiClient,
    String token, {
    String? deviceType,
  }) async {
    try {
      final response = await apiClient.dio.post(
        ApiEndpoints.deviceToken,
        data: {
          'token': token,
          'device_type': deviceType ?? _deviceType,
        },
      );
      _currentToken = token;
      return (response.statusCode ?? 500) >= 200 && (response.statusCode ?? 500) < 300;
    } catch (_) {
      return false;
    }
  }

  /// Unregister FCM device token on logout
  Future<bool> unregisterDeviceToken(
    ApiClient apiClient,
    String token,
  ) async {
    try {
      final response = await apiClient.dio.delete(
        ApiEndpoints.deviceToken,
        data: {
          'token': token,
        },
      );
      if (_currentToken == token) {
        _currentToken = null;
      }
      return (response.statusCode ?? 500) >= 200 && (response.statusCode ?? 500) < 300;
    } catch (_) {
      return false;
    }
  }

  /// Register callback for incoming notifications
  StreamSubscription<FcmNotificationPayload> onNotificationReceived(
      void Function(FcmNotificationPayload payload) callback) {
    return _onMessageController.stream.listen(callback);
  }

  /// Register callback for notification clicks / taps
  StreamSubscription<FcmNotificationPayload> onNotificationTapped(
      void Function(FcmNotificationPayload payload) callback) {
    return _onOpenedAppController.stream.listen(callback);
  }

  /// Dispatch raw incoming message (from FirebaseMessaging.onMessage or test harness)
  void handleIncomingMessage(Map<String, dynamic> rawData) {
    final payload = FcmNotificationPayload.fromMap(rawData);
    _onMessageController.add(payload);
  }

  /// Dispatch raw opened notification (from FirebaseMessaging.onMessageOpenedApp or getInitialMessage)
  void handleNotificationTapped(Map<String, dynamic> rawData) {
    final payload = FcmNotificationPayload.fromMap(rawData);
    _onOpenedAppController.add(payload);
  }

  /// General notification handler
  void handleRawMessage(Map<String, dynamic> rawData, {bool isTapped = false}) {
    if (isTapped) {
      handleNotificationTapped(rawData);
    } else {
      handleIncomingMessage(rawData);
    }
  }

  /// Clean up streams
  Future<void> dispose() async {
    await _onMessageController.close();
    await _onOpenedAppController.close();
    _isInitialized = false;
  }
}

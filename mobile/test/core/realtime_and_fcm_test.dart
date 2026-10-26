import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nexsphere_mobile/core/constants/api_endpoints.dart';
import 'package:nexsphere_mobile/core/fcm/fcm_service.dart';
import 'package:nexsphere_mobile/core/network/api_client.dart';
import 'package:nexsphere_mobile/core/realtime/reverb_websocket_client.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Fake WebSocketChannel for unit testing Pusher protocol without network
class FakeWebSocketChannel implements WebSocketChannel {
  final StreamController<dynamic> incomingController =
      StreamController<dynamic>.broadcast();
  final StreamController<dynamic> outgoingController =
      StreamController<dynamic>.broadcast();

  final List<String> sentMessages = [];

  FakeWebSocketChannel() {
    outgoingController.stream.listen((message) {
      sentMessages.add(message.toString());
    });
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);

  @override
  Stream<dynamic> get stream => incomingController.stream;

  @override
  WebSocketSink get sink => _FakeWebSocketSink(outgoingController);

  @override
  int? get closeCode => null;

  @override
  String? get closeReason => null;

  @override
  String? get protocol => '7';

  @override
  Future<void> get ready => Future.value();

  void simulateIncoming(String jsonMessage) {
    incomingController.add(jsonMessage);
  }

  void simulateClose() {
    incomingController.close();
  }
}

class _FakeWebSocketSink implements WebSocketSink {
  final StreamController<dynamic> _controller;

  _FakeWebSocketSink(this._controller);

  @override
  void add(dynamic data) {
    _controller.add(data);
  }

  @override
  void addError(Object error, [StackTrace? stackTrace]) {
    _controller.addError(error, stackTrace);
  }

  @override
  Future<void> addStream(Stream<dynamic> stream) {
    return _controller.addStream(stream);
  }

  @override
  Future<void> close([int? closeCode, String? closeReason]) async {
    await _controller.close();
  }

  @override
  Future<void> get done => _controller.done;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  group('ApiEndpoints Configuration', () {
    test('verifies notifications and task deletion endpoints', () {
      expect(ApiEndpoints.notifications, equals('/notifications'));
      expect(ApiEndpoints.markNotificationRead(7), equals('/notifications/7/read'));
      expect(ApiEndpoints.markAllNotificationsRead, equals('/notifications/read-all'));
      expect(ApiEndpoints.deviceToken, equals('/devices/token'));
      expect(ApiEndpoints.broadcastingAuth, equals('/broadcasting/auth'));
      expect(ApiEndpoints.deleteChecklist(10, 3), equals('/tasks/10/checklists/3'));
      expect(ApiEndpoints.deleteComment(10, 5), equals('/tasks/10/comments/5'));
    });

    test('verifies Laravel Reverb WebSocket default configuration', () {
      expect(ApiEndpoints.reverbHost, equals('projectmanagement.nexsphere.web.id'));
      expect(ApiEndpoints.reverbPort, equals(443));
      expect(ApiEndpoints.reverbScheme, equals('wss'));
      expect(ApiEndpoints.reverbKey, equals('nxp_prod_key_771829'));
    });
  });

  group('ReverbWebSocketClient URI Building', () {
    test('builds standard production URI correctly', () {
      final client = ReverbWebSocketClient();
      final uri = client.buildUri();

      expect(uri.scheme, equals('wss'));
      expect(uri.host, equals('projectmanagement.nexsphere.web.id'));
      expect(uri.path, equals('/app/nxp_prod_key_771829'));
      expect(uri.queryParameters['protocol'], equals('7'));
      expect(uri.queryParameters['client'], equals('flutter'));
      expect(uri.queryParameters['version'], equals('1.0.0'));
      expect(uri.queryParameters['flash'], equals('false'));
    });

    test('builds custom dev URI with port correctly', () {
      final client = ReverbWebSocketClient(
        host: '127.0.0.1',
        port: 8080,
        scheme: 'ws',
        appKey: 'test_dev_key',
      );
      final uri = client.buildUri();

      expect(uri.scheme, equals('ws'));
      expect(uri.host, equals('127.0.0.1'));
      expect(uri.port, equals(8080));
      expect(uri.path, equals('/app/test_dev_key'));
    });
  });

  group('ReverbWebSocketClient Protocol & Event Routing', () {
    late FakeWebSocketChannel fakeChannel;
    late ReverbWebSocketClient client;

    setUp(() {
      fakeChannel = FakeWebSocketChannel();
      client = ReverbWebSocketClient(
        channelFactory: (_) => fakeChannel,
        customAuthorizer: (socketId, channelName) async {
          return {
            'auth': '$socketId:signature_for_$channelName',
          };
        },
      );
    });

    tearDown(() async {
      await client.dispose();
    });

    test('connects and processes connection_established handshake', () async {
      expect(client.connectionState, equals(ReverbConnectionState.disconnected));

      final stateHistory = <String>[];
      final sub = client.connectionStateStream.listen(stateHistory.add);

      await client.connect();
      expect(client.connectionState, equals(ReverbConnectionState.connecting));

      // Simulate server sending pusher:connection_established
      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:connection_established',
        'data': jsonEncode({
          'socket_id': '12345.67890',
          'activity_timeout': 30,
        }),
      }));

      // Allow microtasks to complete
      await pumpEventQueue();

      expect(client.connectionState, equals(ReverbConnectionState.connected));
      expect(client.socketId, equals('12345.67890'));
      expect(stateHistory, contains(ReverbConnectionState.connected));

      await sub.cancel();
    });

    test('responds to pusher:ping with pusher:pong', () async {
      await client.connect();

      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:connection_established',
        'data': jsonEncode({'socket_id': '111.222', 'activity_timeout': 30}),
      }));
      await pumpEventQueue();

      fakeChannel.sentMessages.clear();

      // Server pings client
      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:ping',
        'data': {},
      }));
      await pumpEventQueue();

      expect(fakeChannel.sentMessages, hasLength(1));
      final pong = jsonDecode(fakeChannel.sentMessages.first);
      expect(pong['event'], equals('pusher:pong'));
      expect(pong['data'], equals({}));
    });

    test('subscribes to public channel and formats payload correctly', () async {
      await client.connect();

      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:connection_established',
        'data': jsonEncode({'socket_id': '111.222'}),
      }));
      await pumpEventQueue();

      fakeChannel.sentMessages.clear();

      client.subscribePublic('space.42');
      await pumpEventQueue();

      expect(fakeChannel.sentMessages, hasLength(1));
      final subPayload = jsonDecode(fakeChannel.sentMessages.first);
      expect(subPayload['event'], equals('pusher:subscribe'));
      expect(subPayload['data']['channel'], equals('space.42'));
      expect(client.isSubscribed('space.42'), isTrue);
    });

    test('authorizes and subscribes to private channels with auth signature', () async {
      await client.connect();

      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:connection_established',
        'data': jsonEncode({'socket_id': '999.888'}),
      }));
      await pumpEventQueue();

      fakeChannel.sentMessages.clear();

      // Subscribe to private space channel
      client.subscribePrivateSpace(7);
      await pumpEventQueue();

      expect(fakeChannel.sentMessages, hasLength(1));
      final subPayload = jsonDecode(fakeChannel.sentMessages.first);
      expect(subPayload['event'], equals('pusher:subscribe'));
      expect(subPayload['data']['channel'], equals('private-space.7'));
      expect(subPayload['data']['auth'], equals('999.888:signature_for_private-space.7'));

      // Subscribe to private user channel
      fakeChannel.sentMessages.clear();
      client.subscribePrivateUser(14);
      await pumpEventQueue();

      final userSubPayload = jsonDecode(fakeChannel.sentMessages.first);
      expect(userSubPayload['data']['channel'], equals('private-user.14'));
      expect(userSubPayload['data']['auth'], equals('999.888:signature_for_private-user.14'));
    });

    test('dispatches events and parses json payload correctly', () async {
      await client.connect();

      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:connection_established',
        'data': jsonEncode({'socket_id': '777.666'}),
      }));
      await pumpEventQueue();

      final List<ReverbEvent> taskCreatedEvents = [];
      final List<ReverbEvent> taskMovedEvents = [];
      final List<ReverbEvent> notificationEvents = [];

      // Listen with full class name
      client.on(RealtimeEvents.taskCreated).listen(taskCreatedEvents.add);
      // Listen with short event name
      client.on('TaskMovedEvent').listen(taskMovedEvents.add);
      // Listen for notifications
      client.on(RealtimeEvents.notificationSent).listen(notificationEvents.add);

      // 1. Simulate TaskCreatedEvent
      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'App\\Events\\TaskCreatedEvent',
        'channel': 'private-space.5',
        'data': jsonEncode({
          'task': {'id': 101, 'title': 'Implement WebSockets', 'stage_id': 2},
          'actor': {'id': 1, 'name': 'John Doe'},
        }),
      }));
      await pumpEventQueue();

      expect(taskCreatedEvents, hasLength(1));
      expect(taskCreatedEvents.first.event, equals('App\\Events\\TaskCreatedEvent'));
      expect(taskCreatedEvents.first.channel, equals('private-space.5'));
      expect(taskCreatedEvents.first.dataMap['task']['id'], equals(101));
      expect(taskCreatedEvents.first.dataMap['task']['title'], equals('Implement WebSockets'));

      // 2. Simulate TaskMovedEvent
      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'App\\Events\\TaskMovedEvent',
        'channel': 'private-space.5',
        'data': jsonEncode({
          'task': {'id': 101, 'stage_id': 3, 'position': 1},
          'previous_stage_id': 2,
        }),
      }));
      await pumpEventQueue();

      expect(taskMovedEvents, hasLength(1));
      expect(taskMovedEvents.first.dataMap['previous_stage_id'], equals(2));

      // 3. Simulate NotificationSentEvent
      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'App\\Events\\NotificationSentEvent',
        'channel': 'private-user.1',
        'data': jsonEncode({
          'notification': {
            'id': 201,
            'type': 'task_assigned',
            'data': {'task_id': 101, 'space_id': 5},
          },
          'unread_count': 3,
        }),
      }));
      await pumpEventQueue();

      expect(notificationEvents, hasLength(1));
      expect(notificationEvents.first.dataMap['unread_count'], equals(3));
    });

    test('handles event matching logic cleanly', () {
      expect(
        ReverbWebSocketClient.matchesEvent('App\\Events\\TaskCreatedEvent', 'TaskCreatedEvent'),
        isTrue,
      );
      expect(
        ReverbWebSocketClient.matchesEvent('.App\\Events\\TaskCreatedEvent', 'TaskCreatedEvent'),
        isTrue,
      );
      expect(
        ReverbWebSocketClient.matchesEvent('App\\Events\\TaskCreatedEvent', 'App\\Events\\TaskCreatedEvent'),
        isTrue,
      );
      expect(
        ReverbWebSocketClient.matchesEvent('TaskDeletedEvent', 'TaskCreatedEvent'),
        isFalse,
      );
    });

    test('unsubscribes and sends pusher:unsubscribe payload', () async {
      await client.connect();
      fakeChannel.simulateIncoming(jsonEncode({
        'event': 'pusher:connection_established',
        'data': jsonEncode({'socket_id': '111.222'}),
      }));
      await pumpEventQueue();

      client.subscribePublic('space.10');
      await pumpEventQueue();
      fakeChannel.sentMessages.clear();

      client.unsubscribe('space.10');
      await pumpEventQueue();

      expect(fakeChannel.sentMessages, hasLength(1));
      final unsubPayload = jsonDecode(fakeChannel.sentMessages.first);
      expect(unsubPayload['event'], equals('pusher:unsubscribe'));
      expect(unsubPayload['data']['channel'], equals('space.10'));
      expect(client.isSubscribed('space.10'), isFalse);
    });
  });

  group('FCM Service & Notification Payload Parsing', () {
    late FcmService fcmService;

    setUp(() async {
      fcmService = FcmService();
      await fcmService.initialize(mockMode: true, initialToken: 'mock_token_init');
    });

    test('initializes gracefully in mock mode without crashing', () {
      expect(fcmService.isInitialized, isTrue);
      expect(fcmService.isMockMode, isTrue);
      expect(fcmService.currentToken, equals('mock_token_init'));
    });

    test('parses nested notification and data payload format correctly', () {
      final rawData = {
        'notification': {
          'title': 'Task Deadline Warning',
          'body': 'Task "Release v1.0" is due in 1 hour',
        },
        'data': {
          'task_id': '45',
          'space_id': '12',
          'space_slug': 'mobile-core',
          'type': 'task_deadline',
        },
      };

      final payload = FcmNotificationPayload.fromMap(rawData);

      expect(payload.taskId, equals(45));
      expect(payload.spaceId, equals(12));
      expect(payload.spaceSlug, equals('mobile-core'));
      expect(payload.type, equals('task_deadline'));
      expect(payload.title, equals('Task Deadline Warning'));
      expect(payload.message, equals('Task "Release v1.0" is due in 1 hour'));
    });

    test('parses flat payload format correctly', () {
      final rawData = {
        'task_id': 99,
        'space_id': 5,
        'space_slug': 'backend-api',
        'type': 'task_comment',
        'title': 'New Comment',
        'message': 'Yusuf commented on your task',
      };

      final payload = FcmNotificationPayload.fromMap(rawData);

      expect(payload.taskId, equals(99));
      expect(payload.spaceId, equals(5));
      expect(payload.spaceSlug, equals('backend-api'));
      expect(payload.type, equals('task_comment'));
      expect(payload.title, equals('New Comment'));
      expect(payload.message, equals('Yusuf commented on your task'));
    });

    test('dispatches incoming notifications to callbacks and stream', () async {
      final List<FcmNotificationPayload> receivedList = [];
      final sub = fcmService.onNotificationReceived(receivedList.add);

      fcmService.handleIncomingMessage({
        'title': 'Checklist Complete',
        'body': 'All items done',
        'data': {'task_id': 88, 'type': 'checklist_completed'},
      });

      await pumpEventQueue();

      expect(receivedList, hasLength(1));
      expect(receivedList.first.title, equals('Checklist Complete'));
      expect(receivedList.first.taskId, equals(88));
      expect(receivedList.first.type, equals('checklist_completed'));

      await sub.cancel();
    });

    test('dispatches tapped notification to onNotificationTapped callback', () async {
      final List<FcmNotificationPayload> tappedList = [];
      final sub = fcmService.onNotificationTapped(tappedList.add);

      fcmService.handleNotificationTapped({
        'title': 'App Opened from Push',
        'body': 'User tapped the notification',
        'data': {'task_id': 77, 'space_slug': 'nexsphere-space'},
      });

      await pumpEventQueue();

      expect(tappedList, hasLength(1));
      expect(tappedList.first.taskId, equals(77));
      expect(tappedList.first.spaceSlug, equals('nexsphere-space'));

      await sub.cancel();
    });

    test('registers and unregisters device token with ApiClient', () async {
      final apiClient = ApiClient();
      final capturedRequests = <RequestOptions>[];

      // Add interceptor to capture and mock responses
      apiClient.dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            capturedRequests.add(options);
            if (options.path == ApiEndpoints.deviceToken) {
              return handler.resolve(
                Response(
                  requestOptions: options,
                  statusCode: 200,
                  data: {'success': true},
                ),
              );
            }
            return handler.next(options);
          },
        ),
      );

      // 1. Register device token
      final regSuccess = await fcmService.registerDeviceToken(
        apiClient,
        'sample_fcm_token_xyz',
        deviceType: 'android',
      );

      expect(regSuccess, isTrue);
      expect(fcmService.currentToken, equals('sample_fcm_token_xyz'));
      expect(capturedRequests, hasLength(1));
      expect(capturedRequests.first.method, equals('POST'));
      expect(capturedRequests.first.path, equals(ApiEndpoints.deviceToken));
      expect(capturedRequests.first.data, equals({
        'token': 'sample_fcm_token_xyz',
        'device_type': 'android',
      }));

      // 2. Unregister device token
      final unregSuccess = await fcmService.unregisterDeviceToken(
        apiClient,
        'sample_fcm_token_xyz',
      );

      expect(unregSuccess, isTrue);
      expect(fcmService.currentToken, isNull);
      expect(capturedRequests, hasLength(2));
      expect(capturedRequests.last.method, equals('DELETE'));
      expect(capturedRequests.last.path, equals(ApiEndpoints.deviceToken));
      expect(capturedRequests.last.data, equals({
        'token': 'sample_fcm_token_xyz',
      }));
    });
  });
}

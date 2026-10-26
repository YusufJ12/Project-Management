import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

import '../constants/api_endpoints.dart';
import '../network/api_client.dart';

/// Connection lifecycle states for [ReverbWebSocketClient]
class ReverbConnectionState {
  static const String disconnected = 'disconnected';
  static const String connecting = 'connecting';
  static const String connected = 'connected';
  static const String reconnecting = 'reconnecting';
  static const String error = 'error';
}

/// Standard Laravel Reverb / Pusher event names
class RealtimeEvents {
  static const String taskCreated = 'App\\Events\\TaskCreatedEvent';
  static const String taskUpdated = 'App\\Events\\TaskUpdatedEvent';
  static const String taskMoved = 'App\\Events\\TaskMovedEvent';
  static const String taskDeleted = 'App\\Events\\TaskDeletedEvent';
  static const String taskChecklistToggled = 'App\\Events\\TaskChecklistToggledEvent';
  static const String taskCommentAdded = 'App\\Events\\TaskCommentAddedEvent';
  static const String taskAttachmentUploaded = 'App\\Events\\TaskAttachmentUploadedEvent';
  static const String taskAttachmentDeleted = 'App\\Events\\TaskAttachmentDeletedEvent';
  static const String notificationSent = 'App\\Events\\NotificationSentEvent';
  static const String spaceStageChanged = 'App\\Events\\SpaceStageChangedEvent';
}

/// Represents an incoming event from Laravel Reverb
class ReverbEvent {
  final String event;
  final String? channel;
  final dynamic data;
  final String rawData;

  ReverbEvent({
    required this.event,
    this.channel,
    required this.data,
    required this.rawData,
  });

  /// Safely converts the payload data to a Map<String, dynamic>
  Map<String, dynamic> get dataMap {
    if (data is Map<String, dynamic>) {
      return data as Map<String, dynamic>;
    } else if (data is Map) {
      return Map<String, dynamic>.from(data as Map);
    }
    return <String, dynamic>{};
  }

  @override
  String toString() =>
      'ReverbEvent(event: $event, channel: $channel, data: $data)';
}

/// Model for tracking channel subscriptions
class _ChannelSubscription {
  final String name;
  final bool isPrivate;
  bool isSubscribed = false;
  String? auth;

  _ChannelSubscription({
    required this.name,
    required this.isPrivate,
  });
}

/// Function signature for creating a [WebSocketChannel]
typedef WebSocketChannelFactory = WebSocketChannel Function(Uri uri);

/// Function signature for custom channel authorization
typedef ChannelAuthorizer = Future<Map<String, dynamic>> Function(
    String socketId, String channelName);

/// Pure-Dart implementation of Pusher Protocol v7 over WebSocket for Laravel Reverb.
class ReverbWebSocketClient {
  final String host;
  final int port;
  final String scheme;
  final String appKey;
  final String authPath;
  final ApiClient? apiClient;
  final ChannelAuthorizer? customAuthorizer;
  final WebSocketChannelFactory _channelFactory;

  WebSocketChannel? _channel;
  StreamSubscription? _channelSubscription;

  String _connectionState = ReverbConnectionState.disconnected;
  final StreamController<String> _connectionStateController =
      StreamController<String>.broadcast();

  final StreamController<ReverbEvent> _eventStreamController =
      StreamController<ReverbEvent>.broadcast();

  final Map<String, _ChannelSubscription> _channels = {};
  String? _socketId;
  int? _activityTimeoutSeconds;
  Timer? _pingTimer;
  Timer? _reconnectTimer;
  bool _isManuallyDisconnected = false;
  int _reconnectAttempts = 0;

  ReverbWebSocketClient({
    this.host = ApiEndpoints.reverbHost,
    this.port = ApiEndpoints.reverbPort,
    this.scheme = ApiEndpoints.reverbScheme,
    this.appKey = ApiEndpoints.reverbKey,
    this.authPath = ApiEndpoints.broadcastingAuth,
    this.apiClient,
    this.customAuthorizer,
    WebSocketChannelFactory? channelFactory,
  }) : _channelFactory = channelFactory ?? ((uri) => WebSocketChannel.connect(uri));

  /// Current connection state string:
  /// 'disconnected' | 'connecting' | 'connected' | 'reconnecting' | 'error'
  String get connectionState => _connectionState;

  /// Stream of connection state changes
  Stream<String> get connectionStateStream => _connectionStateController.stream;

  /// Stream of all incoming Reverb events
  Stream<ReverbEvent> get eventStream => _eventStreamController.stream;

  /// Socket ID assigned by Reverb server
  String? get socketId => _socketId;

  /// Active channel names
  List<String> get activeChannels => _channels.keys.toList();

  /// Build WebSocket URI for Laravel Reverb (Pusher protocol v7)
  Uri buildUri() {
    final isDefaultPort =
        (scheme == 'wss' && port == 443) || (scheme == 'ws' && port == 80);

    return Uri(
      scheme: scheme,
      host: host,
      port: isDefaultPort ? null : port,
      path: '/app/$appKey',
      queryParameters: {
        'protocol': '7',
        'client': 'flutter',
        'version': '1.0.0',
        'flash': 'false',
      },
    );
  }

  /// Connect to the Reverb WebSocket server
  Future<void> connect() async {
    if (_connectionState == ReverbConnectionState.connected ||
        _connectionState == ReverbConnectionState.connecting) {
      return;
    }

    _isManuallyDisconnected = false;
    _setConnectionState(
        _reconnectAttempts > 0 ? ReverbConnectionState.reconnecting : ReverbConnectionState.connecting);

    final uri = buildUri();

    try {
      _channel = _channelFactory(uri);
      _channelSubscription = _channel!.stream.listen(
        _onMessageReceived,
        onError: _onError,
        onDone: _onClosed,
        cancelOnError: true,
      );
    } catch (e) {
      _onError(e);
    }
  }

  /// Disconnect manually from the server
  Future<void> disconnect() async {
    _isManuallyDisconnected = true;
    _cancelTimers();
    _reconnectAttempts = 0;
    _socketId = null;

    for (final channel in _channels.values) {
      channel.isSubscribed = false;
    }

    try {
      await _channelSubscription?.cancel();
      _channelSubscription = null;
      await _channel?.sink.close();
      _channel = null;
    } catch (_) {}

    _setConnectionState(ReverbConnectionState.disconnected);
  }

  /// Subscribe to a public channel (e.g. `space.1`)
  void subscribePublic(String channelName) {
    _subscribeInternal(channelName, isPrivate: false);
  }

  /// Subscribe to a private channel (e.g. `private-space.1` or `space.1`)
  void subscribePrivate(String channelName) {
    final formattedName = channelName.startsWith('private-')
        ? channelName
        : 'private-$channelName';
    _subscribeInternal(formattedName, isPrivate: true);
  }

  /// Convenience helper to subscribe to a space private channel: `private-space.{spaceId}`
  void subscribePrivateSpace(int spaceId) {
    subscribePrivate('space.$spaceId');
  }

  /// Convenience helper to subscribe to a user private channel: `private-user.{userId}`
  void subscribePrivateUser(int userId) {
    subscribePrivate('user.$userId');
  }

  /// General subscribe method
  void subscribe(String channelName, {bool? isPrivate}) {
    final effectiveIsPrivate = isPrivate ?? channelName.startsWith('private-');
    if (effectiveIsPrivate) {
      subscribePrivate(channelName);
    } else {
      subscribePublic(channelName);
    }
  }

  /// Unsubscribe from a channel
  void unsubscribe(String channelName) {
    final subscription = _channels.remove(channelName);
    if (subscription != null && _connectionState == ReverbConnectionState.connected) {
      _sendPayload('pusher:unsubscribe', {'channel': channelName});
    }
  }

  /// Check if client is currently subscribed to a channel
  bool isSubscribed(String channelName) {
    return _channels[channelName]?.isSubscribed ?? false;
  }

  /// Listen for events on a specific channel
  Stream<ReverbEvent> channelStream(String channel) {
    return _eventStreamController.stream.where((e) => e.channel == channel);
  }

  /// Listen for a specific event across all channels or a specific channel
  Stream<ReverbEvent> on(String eventName, {String? channel}) {
    return _eventStreamController.stream.where((e) {
      if (channel != null && e.channel != channel) {
        return false;
      }
      return matchesEvent(e.event, eventName);
    });
  }

  /// Matches event names allowing namespace-agnostic comparison
  /// e.g. 'App\Events\TaskCreatedEvent' matches 'TaskCreatedEvent'
  static bool matchesEvent(String incomingEvent, String expectedEvent) {
    if (incomingEvent == expectedEvent) return true;

    final normIncoming = incomingEvent.startsWith('.')
        ? incomingEvent.substring(1)
        : incomingEvent;
    final normExpected = expectedEvent.startsWith('.')
        ? expectedEvent.substring(1)
        : expectedEvent;

    if (normIncoming == normExpected) return true;

    final shortIncoming = normIncoming.split('\\').last.split('.').last;
    final shortExpected = normExpected.split('\\').last.split('.').last;

    return shortIncoming == shortExpected;
  }

  /// Send a ping message to keep alive
  void sendPing() {
    _sendPayload('pusher:ping', {});
  }

  // --- Internal Protocol Handling ---

  void _onMessageReceived(dynamic raw) {
    try {
      final Map<String, dynamic> message = jsonDecode(raw.toString());
      final String? event = message['event']?.toString();
      final String? channel = message['channel']?.toString();
      final dynamic data = message['data'];

      if (event == null) return;

      if (event == 'pusher:connection_established') {
        _handleConnectionEstablished(data);
      } else if (event == 'pusher:ping') {
        _sendPayload('pusher:pong', {});
      } else if (event == 'pusher:pong') {
        // Pong acknowledged
      } else if (event == 'pusher:error') {
        _setConnectionState(ReverbConnectionState.error);
      } else if (event == 'pusher_internal:subscription_succeeded') {
        if (channel != null && _channels.containsKey(channel)) {
          _channels[channel]!.isSubscribed = true;
        }
      } else {
        // Application or custom event
        dynamic parsedData = data;
        String rawDataStr = '';

        if (data is String) {
          rawDataStr = data;
          try {
            parsedData = jsonDecode(data);
          } catch (_) {
            parsedData = data;
          }
        } else {
          rawDataStr = jsonEncode(data);
        }

        final reverbEvent = ReverbEvent(
          event: event,
          channel: channel,
          data: parsedData,
          rawData: rawDataStr,
        );

        _eventStreamController.add(reverbEvent);
      }
    } catch (_) {
      // Ignore unparseable frames
    }
  }

  void _handleConnectionEstablished(dynamic data) {
    Map<String, dynamic> dataMap = {};
    if (data is String) {
      try {
        dataMap = jsonDecode(data) as Map<String, dynamic>;
      } catch (_) {}
    } else if (data is Map) {
      dataMap = Map<String, dynamic>.from(data);
    }

    _socketId = dataMap['socket_id']?.toString();
    _activityTimeoutSeconds = (dataMap['activity_timeout'] as num?)?.toInt() ?? 30;

    _reconnectAttempts = 0;
    _setConnectionState(ReverbConnectionState.connected);
    _startPingTimer();

    // Automatically (re)subscribe to all registered channels
    _subscribeAllChannels();
  }

  void _subscribeInternal(String channelName, {required bool isPrivate}) {
    final sub = _channels.putIfAbsent(
      channelName,
      () => _ChannelSubscription(name: channelName, isPrivate: isPrivate),
    );

    if (_connectionState == ReverbConnectionState.connected && _socketId != null) {
      _executeSubscription(sub);
    }
  }

  Future<void> _subscribeAllChannels() async {
    for (final sub in _channels.values) {
      await _executeSubscription(sub);
    }
  }

  Future<void> _executeSubscription(_ChannelSubscription sub) async {
    if (sub.isPrivate) {
      try {
        final authData = await _authorizeChannel(sub.name);
        final authSignature = authData['auth']?.toString();

        if (authSignature == null) {
          throw Exception('Missing auth signature in response');
        }

        sub.auth = authSignature;

        final Map<String, dynamic> payloadData = {
          'channel': sub.name,
          'auth': authSignature,
        };

        if (authData.containsKey('channel_data')) {
          payloadData['channel_data'] = authData['channel_data'];
        }

        _sendPayload('pusher:subscribe', payloadData);
        sub.isSubscribed = true;
      } catch (e) {
        _setConnectionState(ReverbConnectionState.error);
      }
    } else {
      _sendPayload('pusher:subscribe', {'channel': sub.name});
      sub.isSubscribed = true;
    }
  }

  Future<Map<String, dynamic>> _authorizeChannel(String channelName) async {
    if (customAuthorizer != null) {
      return await customAuthorizer!(_socketId!, channelName);
    }

    if (apiClient == null) {
      throw Exception('Cannot authorize private channel without ApiClient or customAuthorizer');
    }

    // Resolve URL for broadcasting auth endpoint
    final authUrl = _resolveAuthUrl();

    final response = await apiClient!.dio.post(
      authUrl,
      data: {
        'socket_id': _socketId,
        'channel_name': channelName,
      },
      options: Options(
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    if (response.data is Map<String, dynamic>) {
      return response.data as Map<String, dynamic>;
    } else if (response.data is Map) {
      return Map<String, dynamic>.from(response.data as Map);
    } else if (response.data is String) {
      return jsonDecode(response.data as String) as Map<String, dynamic>;
    }

    throw Exception('Unexpected auth response format: ${response.data}');
  }

  String _resolveAuthUrl() {
    if (authPath.startsWith('http://') || authPath.startsWith('https://')) {
      return authPath;
    }

    final baseUrl = apiClient?.dio.options.baseUrl ?? ApiEndpoints.baseUrl;
    final normalizedBase = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    final normalizedPath = authPath.startsWith('/') ? authPath : '/$authPath';
    return '$normalizedBase$normalizedPath';
  }

  void _sendPayload(String event, Map<String, dynamic> data) {
    if (_channel == null) return;
    try {
      final payload = jsonEncode({'event': event, 'data': data});
      _channel!.sink.add(payload);
    } catch (_) {}
  }

  void _startPingTimer() {
    _pingTimer?.cancel();
    final interval = Duration(seconds: max(5, (_activityTimeoutSeconds ?? 30) - 5));
    _pingTimer = Timer.periodic(interval, (_) => sendPing());
  }

  void _onError(dynamic error) {
    if (_isManuallyDisconnected) return;
    _setConnectionState(ReverbConnectionState.error);
    _scheduleReconnect();
  }

  void _onClosed() {
    if (_isManuallyDisconnected) return;
    _setConnectionState(ReverbConnectionState.reconnecting);
    _scheduleReconnect();
  }

  void _scheduleReconnect() {
    _cancelTimers();
    if (_isManuallyDisconnected) return;

    _reconnectAttempts++;
    final delaySeconds = min(pow(2, _reconnectAttempts).toInt(), 30);

    _setConnectionState(ReverbConnectionState.reconnecting);

    _reconnectTimer = Timer(Duration(seconds: delaySeconds), () {
      if (!_isManuallyDisconnected) {
        connect();
      }
    });
  }

  void _cancelTimers() {
    _pingTimer?.cancel();
    _pingTimer = null;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
  }

  void _setConnectionState(String state) {
    if (_connectionState != state) {
      _connectionState = state;
      if (!_connectionStateController.isClosed) {
        _connectionStateController.add(state);
      }
    }
  }

  /// Clean up all resources
  Future<void> dispose() async {
    await disconnect();
    await _connectionStateController.close();
    await _eventStreamController.close();
  }
}

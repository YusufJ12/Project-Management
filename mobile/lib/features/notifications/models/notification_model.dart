class NotificationModel {
  final int id;
  final int? workspaceId;
  final int userId;
  final String type;
  final String title;
  final String message;
  final int? taskId;
  final String? spaceSlug;
  final String? url;
  final String? actorName;
  final String? actorAvatar;
  final String? readAt;
  final String createdAt;

  NotificationModel({
    required this.id,
    this.workspaceId,
    required this.userId,
    required this.type,
    required this.title,
    required this.message,
    this.taskId,
    this.spaceSlug,
    this.url,
    this.actorName,
    this.actorAvatar,
    this.readAt,
    required this.createdAt,
  });

  bool get isRead => readAt != null && readAt!.isNotEmpty;

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic>
        ? json['data'] as Map<String, dynamic>
        : (json['data'] is Map ? Map<String, dynamic>.from(json['data'] as Map) : <String, dynamic>{});

    int? parsedTaskId;
    final rawTaskId = data['task_id'] ?? json['task_id'];
    if (rawTaskId is int) {
      parsedTaskId = rawTaskId;
    } else if (rawTaskId is String) {
      parsedTaskId = int.tryParse(rawTaskId);
    }

    return NotificationModel(
      id: json['id'] as int? ?? 0,
      workspaceId: json['workspace_id'] as int?,
      userId: json['user_id'] as int? ?? 0,
      type: json['type'] as String? ?? 'general',
      title: (data['title'] ?? json['title'] ?? 'Notifikasi').toString(),
      message: (data['message'] ?? json['message'] ?? '').toString(),
      taskId: parsedTaskId,
      spaceSlug: (data['space_slug'] ?? json['space_slug'])?.toString(),
      url: (data['url'] ?? json['url'])?.toString(),
      actorName: (data['actor_name'] ?? json['actor_name'])?.toString(),
      actorAvatar: (data['actor_avatar'] ?? json['actor_avatar'])?.toString(),
      readAt: json['read_at']?.toString(),
      createdAt: (json['created_at'] ?? '').toString(),
    );
  }

  NotificationModel copyWith({
    int? id,
    int? workspaceId,
    int? userId,
    String? type,
    String? title,
    String? message,
    int? taskId,
    String? spaceSlug,
    String? url,
    String? actorName,
    String? actorAvatar,
    String? readAt,
    String? createdAt,
  }) {
    return NotificationModel(
      id: id ?? this.id,
      workspaceId: workspaceId ?? this.workspaceId,
      userId: userId ?? this.userId,
      type: type ?? this.type,
      title: title ?? this.title,
      message: message ?? this.message,
      taskId: taskId ?? this.taskId,
      spaceSlug: spaceSlug ?? this.spaceSlug,
      url: url ?? this.url,
      actorName: actorName ?? this.actorName,
      actorAvatar: actorAvatar ?? this.actorAvatar,
      readAt: readAt ?? this.readAt,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'workspace_id': workspaceId,
      'user_id': userId,
      'type': type,
      'title': title,
      'message': message,
      'task_id': taskId,
      'space_slug': spaceSlug,
      'url': url,
      'actor_name': actorName,
      'actor_avatar': actorAvatar,
      'read_at': readAt,
      'created_at': createdAt,
    };
  }
}

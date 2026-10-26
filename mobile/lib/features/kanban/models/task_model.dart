import '../../auth/models/user_model.dart';

class TaskModel {
  final int id;
  final int listId;
  final int stageId;
  final String title;
  final String? description;
  final String priority;
  final String? startDate;
  final String? dueAt;
  final int? estimateMinutes;
  final int position;
  final String? stageName;
  final String? stageColor;
  final bool isDone;
  final int checklistsCount;
  final int completedChecklistsCount;

  final List<UserModel> assignees;
  final List<dynamic> dependencies;
  final List<TaskCommentModel> comments;
  final List<TaskAttachmentModel> attachments;
  final List<ChecklistModel> checklists;

  TaskModel({
    required this.id,
    required this.listId,
    required this.stageId,
    required this.title,
    this.description,
    required this.priority,
    this.startDate,
    this.dueAt,
    this.estimateMinutes,
    required this.position,
    this.stageName,
    this.stageColor,
    required this.isDone,
    this.checklistsCount = 0,
    this.completedChecklistsCount = 0,
    this.assignees = const [],
    this.dependencies = const [],
    this.comments = const [],
    this.attachments = const [],
    this.checklists = const [],
  });

  factory TaskModel.fromJson(Map<String, dynamic> json) {
    final rawChecklists = (json['checklists'] as List<dynamic>?)
            ?.map((c) => ChecklistModel.fromJson(c as Map<String, dynamic>))
            .toList() ??
        [];
    final calculatedCount = rawChecklists.length;
    final calculatedCompleted = rawChecklists.where((c) => c.isCompleted).length;

    return TaskModel(
      id: json['id'] as int? ?? 0,
      listId: json['list_id'] as int? ?? 0,
      stageId: json['stage_id'] as int? ?? 0,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      priority: json['priority'] as String? ?? 'medium',
      startDate: json['start_date'] as String? ?? json['startDate'] as String?,
      dueAt: json['due_at'] as String? ?? json['dueAt'] as String?,
      estimateMinutes: json['estimate_minutes'] as int? ?? json['estimateMinutes'] as int?,
      position: json['position'] as int? ?? 1,
      stageName: json['stage_name'] as String? ?? json['stageName'] as String?,
      stageColor: json['stage_color'] as String? ?? json['stageColor'] as String?,
      isDone: json['is_done'] as bool? ?? (json['isDone'] as bool? ?? false),
      checklistsCount: json['checklists_count'] as int? ?? (calculatedCount > 0 ? calculatedCount : 0),
      completedChecklistsCount: json['completed_checklists_count'] as int? ??
          (calculatedCompleted > 0 ? calculatedCompleted : 0),
      assignees: (json['assignees'] as List<dynamic>?)
              ?.map((a) => UserModel.fromJson(a as Map<String, dynamic>))
              .toList() ??
          const [],
      dependencies: (json['dependencies'] as List<dynamic>?) ?? const [],
      comments: (json['comments'] as List<dynamic>?)
              ?.map((c) => TaskCommentModel.fromJson(c as Map<String, dynamic>))
              .toList() ??
          const [],
      attachments: (json['attachments'] as List<dynamic>?)
              ?.map((a) => TaskAttachmentModel.fromJson(a as Map<String, dynamic>))
              .toList() ??
          const [],
      checklists: rawChecklists,
    );
  }

  TaskModel copyWith({
    int? stageId,
    int? position,
    String? title,
    String? description,
    String? priority,
    String? startDate,
    String? dueAt,
    int? estimateMinutes,
    String? stageName,
    String? stageColor,
    bool? isDone,
    int? checklistsCount,
    int? completedChecklistsCount,
    List<UserModel>? assignees,
    List<dynamic>? dependencies,
    List<TaskCommentModel>? comments,
    List<TaskAttachmentModel>? attachments,
    List<ChecklistModel>? checklists,
  }) {
    return TaskModel(
      id: id,
      listId: listId,
      stageId: stageId ?? this.stageId,
      title: title ?? this.title,
      description: description ?? this.description,
      priority: priority ?? this.priority,
      startDate: startDate ?? this.startDate,
      dueAt: dueAt ?? this.dueAt,
      estimateMinutes: estimateMinutes ?? this.estimateMinutes,
      position: position ?? this.position,
      stageName: stageName ?? this.stageName,
      stageColor: stageColor ?? this.stageColor,
      isDone: isDone ?? this.isDone,
      checklistsCount: checklistsCount ?? this.checklistsCount,
      completedChecklistsCount: completedChecklistsCount ?? this.completedChecklistsCount,
      assignees: assignees ?? this.assignees,
      dependencies: dependencies ?? this.dependencies,
      comments: comments ?? this.comments,
      attachments: attachments ?? this.attachments,
      checklists: checklists ?? this.checklists,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'list_id': listId,
      'stage_id': stageId,
      'title': title,
      'description': description,
      'priority': priority,
      'start_date': startDate,
      'due_at': dueAt,
      'estimate_minutes': estimateMinutes,
      'position': position,
      'stage_name': stageName,
      'stage_color': stageColor,
      'is_done': isDone,
      'checklists_count': checklistsCount,
      'completed_checklists_count': completedChecklistsCount,
      'assignees': assignees.map((a) => a.toJson()).toList(),
      'dependencies': dependencies,
      'comments': comments.map((c) => c.toJson()).toList(),
      'attachments': attachments.map((a) => a.toJson()).toList(),
      'checklists': checklists.map((c) => c.toJson()).toList(),
    };
  }
}

class ChecklistModel {
  final int id;
  final int? taskId;
  final String title;
  final bool isCompleted;
  final int? position;

  ChecklistModel({
    required this.id,
    this.taskId,
    required this.title,
    required this.isCompleted,
    this.position,
  });

  factory ChecklistModel.fromJson(Map<String, dynamic> json) {
    return ChecklistModel(
      id: json['id'] as int? ?? 0,
      taskId: json['task_id'] as int?,
      title: json['title'] as String? ?? '',
      isCompleted: json['is_completed'] as bool? ?? false,
      position: json['position'] as int?,
    );
  }

  ChecklistModel copyWith({
    int? id,
    int? taskId,
    String? title,
    bool? isCompleted,
    int? position,
  }) {
    return ChecklistModel(
      id: id ?? this.id,
      taskId: taskId ?? this.taskId,
      title: title ?? this.title,
      isCompleted: isCompleted ?? this.isCompleted,
      position: position ?? this.position,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'task_id': taskId,
      'title': title,
      'is_completed': isCompleted,
      'position': position,
    };
  }
}

class TaskCommentModel {
  final int id;
  final int taskId;
  final int userId;
  final String userName;
  final String? userAvatar;
  final String content;
  final String createdAt;

  TaskCommentModel({
    required this.id,
    required this.taskId,
    required this.userId,
    required this.userName,
    this.userAvatar,
    required this.content,
    required this.createdAt,
  });

  factory TaskCommentModel.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>?;
    return TaskCommentModel(
      id: json['id'] as int? ?? 0,
      taskId: json['task_id'] as int? ?? (json['taskId'] as int? ?? 0),
      userId: json['user_id'] as int? ?? (user?['id'] as int? ?? 0),
      userName: json['user_name'] as String? ?? (user?['name'] as String? ?? 'Pengguna'),
      userAvatar: json['user_avatar'] as String? ?? (user?['avatar_path'] as String?),
      content: json['content'] as String? ?? '',
      createdAt: json['created_at'] as String? ?? (json['createdAt'] as String? ?? ''),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'task_id': taskId,
      'user_id': userId,
      'user_name': userName,
      'user_avatar': userAvatar,
      'content': content,
      'created_at': createdAt,
    };
  }
}

class TaskAttachmentModel {
  final int id;
  final int taskId;
  final String originalName;
  final int fileSize;
  final String mimeType;
  final String? downloadUrl;
  final String createdAt;

  TaskAttachmentModel({
    required this.id,
    required this.taskId,
    required this.originalName,
    required this.fileSize,
    required this.mimeType,
    this.downloadUrl,
    required this.createdAt,
  });

  factory TaskAttachmentModel.fromJson(Map<String, dynamic> json) {
    final id = json['id'] as int? ?? 0;
    final taskId = json['task_id'] as int? ?? (json['taskId'] as int? ?? 0);
    return TaskAttachmentModel(
      id: id,
      taskId: taskId,
      originalName: json['original_name'] as String? ??
          (json['originalName'] as String? ?? 'Berkas'),
      fileSize: json['size_bytes'] as int? ??
          (json['file_size'] as int? ?? (json['fileSize'] as int? ?? 0)),
      mimeType: json['detected_mime'] as String? ??
          (json['mime_type'] as String? ?? (json['mimeType'] as String? ?? 'application/octet-stream')),
      downloadUrl: json['download_url'] as String? ??
          (json['downloadUrl'] as String? ?? '/tasks/$taskId/attachments/$id/download'),
      createdAt: json['created_at'] as String? ?? (json['createdAt'] as String? ?? ''),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'task_id': taskId,
      'original_name': originalName,
      'file_size': fileSize,
      'mime_type': mimeType,
      'download_url': downloadUrl,
      'created_at': createdAt,
    };
  }
}

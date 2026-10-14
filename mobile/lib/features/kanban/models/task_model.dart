class TaskModel {
  final int id;
  final int listId;
  final int stageId;
  final String title;
  final String? description;
  final String priority;
  final String? dueAt;
  final int? estimateMinutes;
  final int position;
  final String? stageName;
  final String? stageColor;
  final bool isDone;
  final int checklistsCount;
  final int completedChecklistsCount;

  final List<ChecklistModel> checklists;

  TaskModel({
    required this.id,
    required this.listId,
    required this.stageId,
    required this.title,
    this.description,
    required this.priority,
    this.dueAt,
    this.estimateMinutes,
    required this.position,
    this.stageName,
    this.stageColor,
    required this.isDone,
    this.checklistsCount = 0,
    this.completedChecklistsCount = 0,
    this.checklists = const [],
  });

  factory TaskModel.fromJson(Map<String, dynamic> json) {
    return TaskModel(
      id: json['id'] as int,
      listId: json['list_id'] as int,
      stageId: json['stage_id'] as int,
      title: json['title'] as String,
      description: json['description'] as String?,
      priority: json['priority'] as String? ?? 'medium',
      dueAt: json['due_at'] as String?,
      estimateMinutes: json['estimate_minutes'] as int?,
      position: json['position'] as int? ?? 1,
      stageName: json['stage_name'] as String?,
      stageColor: json['stage_color'] as String?,
      isDone: json['is_done'] as bool? ?? false,
      checklistsCount: json['checklists_count'] as int? ?? 0,
      completedChecklistsCount: json['completed_checklists_count'] as int? ?? 0,
      checklists: (json['checklists'] as List<dynamic>?)
              ?.map((c) => ChecklistModel.fromJson(c as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  TaskModel copyWith({
    int? stageId,
    int? position,
    String? title,
    String? priority,
  }) {
    return TaskModel(
      id: id,
      listId: listId,
      stageId: stageId ?? this.stageId,
      title: title ?? this.title,
      description: description,
      priority: priority ?? this.priority,
      dueAt: dueAt,
      estimateMinutes: estimateMinutes,
      position: position ?? this.position,
      stageName: stageName,
      stageColor: stageColor,
      isDone: isDone,
      checklistsCount: checklistsCount,
      completedChecklistsCount: completedChecklistsCount,
      checklists: checklists,
    );
  }
}

class ChecklistModel {
  final int id;
  final String title;
  final bool isCompleted;

  ChecklistModel({
    required this.id,
    required this.title,
    required this.isCompleted,
  });

  factory ChecklistModel.fromJson(Map<String, dynamic> json) {
    return ChecklistModel(
      id: json['id'] as int,
      title: json['title'] as String,
      isCompleted: json['is_completed'] as bool? ?? false,
    );
  }
}


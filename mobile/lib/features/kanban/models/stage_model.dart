import 'task_model.dart';

class StageModel {
  final int id;
  final int listId;
  final String name;
  final String color;
  final bool isDone;
  final int position;
  final List<TaskModel> tasks;

  StageModel({
    required this.id,
    required this.listId,
    required this.name,
    required this.color,
    required this.isDone,
    required this.position,
    this.tasks = const [],
  });

  factory StageModel.fromJson(Map<String, dynamic> json) {
    return StageModel(
      id: json['id'] as int,
      listId: json['list_id'] as int,
      name: json['name'] as String,
      color: json['color'] as String? ?? '#64748b',
      isDone: json['is_done'] as bool? ?? false,
      position: json['position'] as int? ?? 1,
      tasks: (json['tasks'] as List<dynamic>?)
              ?.map((t) => TaskModel.fromJson(t as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}


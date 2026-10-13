import 'package:dio/dio.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../models/stage_model.dart';
import '../models/task_model.dart';

class KanbanRepository {
  final ApiClient _apiClient;

  KanbanRepository(this._apiClient);

  /// Fetch full space detail including lists, stages, and tasks
  Future<List<StageModel>> getSpaceStages(int spaceId) async {
    final response = await _apiClient.dio.get(ApiEndpoints.spaceDetail(spaceId));
    final data = response.data['data'] as Map<String, dynamic>;
    final stagesJson = data['stages'] as List<dynamic>? ?? [];

    return stagesJson
        .map((s) => StageModel.fromJson(s as Map<String, dynamic>))
        .toList();
  }

  /// Create new task via mobile API
  Future<TaskModel> createTask({
    required int listId,
    required int stageId,
    required String title,
    String? priority,
    String? description,
    String? dueAt,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.tasks,
      data: {
        'list_id': listId,
        'stage_id': stageId,
        'title': title,
        'priority': priority ?? 'medium',
        'description': description,
        'due_at': dueAt,
      },
    );

    return TaskModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  /// Move task between stages or reorder within same stage
  Future<TaskModel> moveTask({
    required int taskId,
    required int stageId,
    required int position,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.moveTask(taskId),
      data: {
        'stage_id': stageId,
        'position': position,
      },
    );

    return TaskModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  /// Toggle checklist subtask item
  Future<bool> toggleChecklist(int taskId, int checklistId) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.toggleChecklist(taskId, checklistId),
    );

    return response.data['data']['is_completed'] as bool? ?? false;
  }
}


import 'package:dio/dio.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../notifications/models/notification_model.dart';
import '../models/stage_model.dart';
import '../models/task_model.dart';

class KanbanRepository {
  final ApiClient _apiClient;

  KanbanRepository(this._apiClient);

  ApiClient get apiClient => _apiClient;

  /// Fetch full space detail including lists, stages, and tasks
  Future<List<StageModel>> getSpaceStages(int spaceId) async {
    final response = await _apiClient.dio.get(ApiEndpoints.spaceDetail(spaceId));
    final data = response.data['data'] as Map<String, dynamic>;
    final stagesJson = data['stages'] as List<dynamic>? ?? [];

    return stagesJson
        .map((s) => StageModel.fromJson(s as Map<String, dynamic>))
        .toList();
  }

  /// Get detailed task with checklists, assignees, comments, attachments
  Future<TaskModel> getTaskDetail(int taskId) async {
    final response = await _apiClient.dio.get(ApiEndpoints.taskDetail(taskId));
    final data = response.data['data'] as Map<String, dynamic>;
    return TaskModel.fromJson(data);
  }

  /// Create new task via mobile API
  Future<TaskModel> createTask({
    required int listId,
    required int stageId,
    required String title,
    String? priority,
    String? description,
    String? startDate,
    String? dueAt,
    List<int>? assigneeIds,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.tasks,
      data: {
        'list_id': listId,
        'stage_id': stageId,
        'title': title,
        'priority': priority ?? 'medium',
        'description': description,
        if (startDate != null) 'start_date': startDate,
        if (dueAt != null) 'due_at': dueAt,
        if (assigneeIds != null && assigneeIds.isNotEmpty) 'assignee_ids': assigneeIds,
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

  /// Add new checklist item to task
  Future<ChecklistModel> addChecklist(int taskId, String title) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.taskChecklists(taskId),
      data: {'title': title},
    );

    final data = response.data['data'] as Map<String, dynamic>;
    return ChecklistModel.fromJson(data);
  }

  /// Delete checklist item
  Future<bool> deleteChecklist(int taskId, int checklistId) async {
    final response = await _apiClient.dio.delete(
      ApiEndpoints.deleteChecklist(taskId, checklistId),
    );

    return response.statusCode == 200;
  }

  /// Add comment to task
  Future<TaskCommentModel> addComment(int taskId, String content) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.taskComments(taskId),
      data: {'content': content},
    );

    final data = (response.data['data'] ?? response.data['comment']) as Map<String, dynamic>;
    return TaskCommentModel.fromJson(data);
  }

  /// Delete comment from task
  Future<bool> deleteComment(int taskId, int commentId) async {
    final response = await _apiClient.dio.delete(
      ApiEndpoints.deleteComment(taskId, commentId),
    );

    return response.statusCode == 200;
  }

  /// Upload file attachment using FormData
  Future<TaskAttachmentModel> uploadAttachment(
    int taskId,
    String filePath,
    String fileName,
  ) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: fileName),
    });

    final response = await _apiClient.dio.post(
      ApiEndpoints.taskAttachments(taskId),
      data: formData,
    );

    final data = (response.data['attachment'] ?? response.data['data']) as Map<String, dynamic>;
    return TaskAttachmentModel.fromJson(data);
  }

  /// Upload in-memory attachment using FormData bytes
  Future<TaskAttachmentModel> uploadAttachmentBytes(
    int taskId,
    List<int> bytes,
    String fileName,
  ) async {
    final formData = FormData.fromMap({
      'file': MultipartFile.fromBytes(bytes, filename: fileName),
    });

    final response = await _apiClient.dio.post(
      ApiEndpoints.taskAttachments(taskId),
      data: formData,
    );

    final data = (response.data['attachment'] ?? response.data['data']) as Map<String, dynamic>;
    return TaskAttachmentModel.fromJson(data);
  }

  /// Delete attachment from task
  Future<bool> deleteAttachment(int taskId, int attachmentId) async {
    final response = await _apiClient.dio.delete(
      ApiEndpoints.taskAttachmentDetail(taskId, attachmentId),
    );

    return response.statusCode == 200;
  }

  /// Update task title, description, priority, dates, stage, assignees
  Future<TaskModel> updateTask({
    required int taskId,
    required String title,
    String? description,
    String? priority,
    String? startDate,
    String? dueAt,
    int? stageId,
    List<int>? assigneeIds,
  }) async {
    final payload = <String, dynamic>{
      'title': title,
      if (description != null) 'description': description,
      if (priority != null) 'priority': priority,
      if (startDate != null) 'start_date': startDate,
      if (dueAt != null) 'due_at': dueAt,
      if (stageId != null) 'stage_id': stageId,
      if (assigneeIds != null) 'assignee_ids': assigneeIds,
    };

    final response = await _apiClient.dio.put(
      ApiEndpoints.taskDetail(taskId),
      data: payload,
    );

    return TaskModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  /// Delete task
  Future<bool> deleteTask(int taskId) async {
    final response = await _apiClient.dio.delete(ApiEndpoints.taskDetail(taskId));
    return response.statusCode == 200;
  }

  /// Fetch notifications for current user
  Future<List<NotificationModel>> getNotifications() async {
    final response = await _apiClient.dio.get(ApiEndpoints.notifications);
    final data = response.data;
    List<dynamic> list = [];
    if (data is Map<String, dynamic>) {
      if (data['notifications'] is List) {
        list = data['notifications'] as List<dynamic>;
      } else if (data['data'] is List) {
        list = data['data'] as List<dynamic>;
      }
    } else if (data is List) {
      list = data;
    }

    return list
        .map((n) => NotificationModel.fromJson(n as Map<String, dynamic>))
        .toList();
  }

  /// Mark specific notification as read
  Future<bool> markNotificationRead(int id) async {
    final response = await _apiClient.dio.post(ApiEndpoints.markNotificationRead(id));
    return response.statusCode == 200;
  }

  /// Mark all notifications as read
  Future<bool> markAllNotificationsRead() async {
    final response = await _apiClient.dio.post(ApiEndpoints.markAllNotificationsRead);
    return response.statusCode == 200;
  }
}

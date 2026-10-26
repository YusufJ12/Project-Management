class ApiEndpoints {
  // Default Base URL langsung mengarah ke server produksi NexSphere
  static const String baseUrl = 'https://projectmanagement.nexsphere.web.id/api/v1';

  // Auth Endpoints
  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String me = '/auth/me';
  static const String logout = '/auth/logout';

  // Workspaces & Spaces CRUD
  static const String workspaces = '/workspaces';
  static String workspaceDetail(int workspaceId) => '/workspaces/$workspaceId';
  static String workspaceSpaces(int workspaceId) => '/workspaces/$workspaceId/spaces';
  static String spaceDetail(int spaceId) => '/spaces/$spaceId';
  static String spaceTimeline(int spaceId) => '/spaces/$spaceId/timeline';

  // Tasks & Kanban
  static const String tasks = '/tasks';
  static const String myTasks = '/my-tasks';
  static String taskDetail(int taskId) => '/tasks/$taskId';
  static String moveTask(int taskId) => '/tasks/$taskId/move';
  static String taskChecklists(int taskId) => '/tasks/$taskId/checklists';
  static String toggleChecklist(int taskId, int checklistId) =>
      '/tasks/$taskId/checklists/$checklistId/toggle';
  static String deleteChecklist(int taskId, int checklistId) =>
      '/tasks/$taskId/checklists/$checklistId';
  static String taskComments(int taskId) => '/tasks/$taskId/comments';
  static String deleteComment(int taskId, int commentId) =>
      '/tasks/$taskId/comments/$commentId';

  // Attachments
  static String taskAttachments(int taskId) => '/tasks/$taskId/attachments';
  static String taskAttachmentDetail(int taskId, int attachmentId) =>
      '/tasks/$taskId/attachments/$attachmentId';

  // Notifications
  static const String notifications = '/notifications';
  static String markNotificationRead(int id) => '/notifications/$id/read';
  static const String markAllNotificationsRead = '/notifications/read-all';

  // Device Tokens (FCM)
  static const String deviceToken = '/devices/token';

  // Realtime & Broadcasting
  static const String broadcastingAuth = '/broadcasting/auth';

  // Reverb WebSocket Configuration
  static const String reverbHost = 'projectmanagement.nexsphere.web.id';
  static const int reverbPort = 443;
  static const String reverbScheme = 'wss';
  static const String reverbKey = 'nxp_prod_key_771829';

  // Analytics & Collaboration
  static const String dashboard = '/dashboard';
  static const String activityLogs = '/activity-logs';
}


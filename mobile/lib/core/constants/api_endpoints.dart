class ApiEndpoints {
  // Default Base URL langsung mengarah ke server produksi NexSphere
  static const String baseUrl = 'https://projectmanagement.nexsphere.web.id/api/v1';

  // Auth Endpoints
  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String me = '/auth/me';
  static const String logout = '/auth/logout';

  // Workspaces & Spaces
  static const String workspaces = '/workspaces';
  static String workspaceSpaces(int workspaceId) => '/workspaces/$workspaceId/spaces';
  static String spaceDetail(int spaceId) => '/spaces/$spaceId';

  // Tasks & Kanban
  static const String tasks = '/tasks';
  static String taskDetail(int taskId) => '/tasks/$taskId';
  static String moveTask(int taskId) => '/tasks/$taskId/move';
  static String taskChecklists(int taskId) => '/tasks/$taskId/checklists';
  static String toggleChecklist(int taskId, int checklistId) =>
      '/tasks/$taskId/checklists/$checklistId/toggle';
  static String taskComments(int taskId) => '/tasks/$taskId/comments';
}


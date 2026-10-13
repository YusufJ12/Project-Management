class ApiEndpoints {
  // Ganti baseUrl sesuai target environment:
  // - Android Emulator: http://10.0.2.2:8000/api/v1
  // - iOS Simulator: http://localhost:8000/api/v1
  // - Production Shared Hosting: https://nama-domain-anda.com/api/v1
  static const String baseUrl = 'http://10.0.2.2:8000/api/v1';

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


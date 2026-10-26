import 'dart:async';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'core/constants/api_endpoints.dart';
import 'core/fcm/fcm_service.dart';
import 'core/network/api_client.dart';
import 'core/realtime/reverb_websocket_client.dart';
import 'features/auth/models/user_model.dart';
import 'features/kanban/models/stage_model.dart';
import 'features/kanban/models/task_model.dart';
import 'features/kanban/repositories/kanban_repository.dart';
import 'features/notifications/presentation/notifications_sheet.dart';
import 'features/tasks/presentation/task_detail_modal.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp();
    }
  } catch (e) {
    debugPrint('Firebase.initializeApp warning: $e');
  }
  runApp(const NexSphereApp());
}

class NexSphereApp extends StatelessWidget {
  const NexSphereApp({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = ApiClient();
    final kanbanRepo = KanbanRepository(apiClient);

    return MaterialApp(
      title: 'NexSphere Project Management',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF00AAAD),
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF8FAFC),
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.white,
          foregroundColor: Color(0xFF0F172A),
          elevation: 0,
          scrolledUnderElevation: 1,
        ),
      ),
      darkTheme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF00AAAD),
          brightness: Brightness.dark,
        ),
        scaffoldBackgroundColor: const Color(0xFF0F172A),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF1E293B),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      themeMode: ThemeMode.system,
      home: SplashScreen(apiClient: apiClient, kanbanRepo: kanbanRepo),
    );
  }
}

// ==========================================
// 1. SPLASH SCREEN: Auth check
// ==========================================
class SplashScreen extends StatefulWidget {
  final ApiClient apiClient;
  final KanbanRepository kanbanRepo;

  const SplashScreen({super.key, required this.apiClient, required this.kanbanRepo});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    await Future.delayed(const Duration(milliseconds: 600));
    final hasToken = await widget.apiClient.hasToken();

    if (!mounted) return;

    if (hasToken) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => WorkspaceScreen(
            apiClient: widget.apiClient,
            kanbanRepo: widget.kanbanRepo,
          ),
        ),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => LoginScreen(
            apiClient: widget.apiClient,
            kanbanRepo: widget.kanbanRepo,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Image.asset(
              'assets/images/logo.png',
              width: 80,
              height: 80,
            ),
            const SizedBox(height: 16),
            const Text(
              'NexSphere',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: -0.5),
            ),
            const SizedBox(height: 8),
            const Text('Project Management Mobile', style: TextStyle(color: Colors.grey)),
            const SizedBox(height: 24),
            const CircularProgressIndicator(strokeWidth: 2),
          ],
        ),
      ),
    );
  }
}

// ==========================================
// 2. LOGIN SCREEN
// ==========================================
class LoginScreen extends StatefulWidget {
  final ApiClient apiClient;
  final KanbanRepository kanbanRepo;

  const LoginScreen({super.key, required this.apiClient, required this.kanbanRepo});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  Future<void> _login() async {
    final inputVal = _emailCtrl.text.trim();
    final pass = _passCtrl.text;
    if (inputVal.isEmpty || pass.isEmpty) {
      setState(() {
        _errorMessage = 'Harap isi email/username dan kata sandi.';
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await widget.apiClient.dio.post(
        ApiEndpoints.login,
        data: {
          'login': inputVal,
          'email': inputVal,
          'username': inputVal,
          'password': pass,
        },
      );

      final token = res.data['token'] as String;
      await widget.apiClient.saveToken(token);
      try {
        await FcmService().initialize(apiClient: widget.apiClient);
      } catch (_) {}

      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => WorkspaceScreen(
            apiClient: widget.apiClient,
            kanbanRepo: widget.kanbanRepo,
          ),
        ),
      );
    } on DioException catch (e) {
      String msg = 'Gagal login. Periksa email/username & kata sandi.';
      if (e.response != null) {
        final data = e.response?.data;
        if (data is Map<String, dynamic> && data['message'] != null) {
          msg = data['message'].toString();
        } else if (data is String && data.contains('bot-protection')) {
          msg = 'Server menolak request (Imunify360 Bot-Protection). Coba ubah server URL ke IP lokal atau whitelist IP di hosting.';
        } else if (e.response?.statusCode == 403) {
          msg = 'Akses API diblokir oleh firewall server (403 Forbidden).';
        } else if (e.response?.statusCode == 404) {
          msg = 'Endpoint login tidak ditemukan di server (404 Not Found).';
        }
      } else {
        msg = 'Koneksi gagal: ${e.message ?? e.type.name}';
      }
      setState(() {
        _errorMessage = msg;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Terjadi kesalahan: $e';
      });
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
            child: Container(
              padding: const EdgeInsets.all(24.0),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.grey.withAlpha(35)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withAlpha(8),
                    blurRadius: 20,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF00AAAD).withAlpha(20),
                        shape: BoxShape.circle,
                      ),
                      child: Image.asset(
                        'assets/images/logo.png',
                        width: 56,
                        height: 56,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Masuk ke NexSphere',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Kelola tugas & kolaborasi proyek tim dari mana saja.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                  ),
                  const SizedBox(height: 28),
                  if (_errorMessage != null)
                    Container(
                      padding: const EdgeInsets.all(12),
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(
                        color: Colors.red.withAlpha(25),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.red.withAlpha(80)),
                      ),
                      child: Text(_errorMessage!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                    ),
                  TextField(
                    controller: _emailCtrl,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      labelText: 'Email atau Username',
                      prefixIcon: const Icon(Icons.alternate_email_rounded, size: 20),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _passCtrl,
                    obscureText: true,
                    decoration: InputDecoration(
                      labelText: 'Kata Sandi',
                      prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton(
                    onPressed: _isLoading ? null : _login,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF00AAAD),
                      foregroundColor: Colors.white,
                      elevation: 1,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: _isLoading
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Masuk Sekarang', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ==========================================
// 3. WORKSPACE & SPACES LIST SCREEN
// ==========================================
class WorkspaceScreen extends StatefulWidget {
  final ApiClient apiClient;
  final KanbanRepository kanbanRepo;

  const WorkspaceScreen({super.key, required this.apiClient, required this.kanbanRepo});

  @override
  State<WorkspaceScreen> createState() => _WorkspaceScreenState();
}

class _WorkspaceScreenState extends State<WorkspaceScreen> with WidgetsBindingObserver {
  bool _isLoading = true;
  String _activeTab = 'dashboard'; // 'dashboard', 'spaces', 'my_tasks', 'timeline', 'activity_logs'

  List<dynamic> _workspaces = [];
  Map<String, dynamic>? _selectedWorkspace;
  List<dynamic> _spaces = [];
  String? _userName;
  String? _userEmail;
  UserModel? _currentUser;
  int _unreadNotificationsCount = 0;

  StreamSubscription<FcmNotificationPayload>? _fcmMessageSubscription;
  StreamSubscription<FcmNotificationPayload>? _fcmTapSubscription;

  // Dashboard Metrics
  Map<String, dynamic>? _dashboardData;
  bool _isDashboardLoading = false;

  // My Tasks
  List<dynamic> _myTasks = [];
  bool _isMyTasksLoading = false;
  String _myTasksFilter = 'all'; // 'all', 'active', 'completed'

  // Activity Logs
  List<dynamic> _activityLogs = [];
  bool _isLogsLoading = false;
  String _activityLogFilter = 'all';

  // Timeline
  List<dynamic> _timelineTasks = [];
  bool _isTimelineLoading = false;
  int? _timelineSpaceId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initFcmAndNotifications();
    _loadInitialData();
  }

  Future<void> _initFcmAndNotifications() async {
    // 1. Inisialisasi FCM & Register Token ke Server
    try {
      await FcmService().initialize(apiClient: widget.apiClient);
    } catch (e) {
      debugPrint('[FCM] init in WorkspaceScreen error: $e');
    }

    // 2. Foreground FCM listener (badge increment + sleek in-app banner)
    _fcmMessageSubscription = FcmService().onMessageStream.listen((payload) {
      if (!mounted) return;
      _fetchUnreadNotificationsCount();

      // Auto-refresh active tab jika berkaitan
      if (_activeTab == 'my_tasks') _loadMyTasks();
      if (_activeTab == 'timeline' && _timelineSpaceId != null) {
        _loadTimeline(_timelineSpaceId!);
      }
      if (_activeTab == 'dashboard') _loadDashboard();

      // Tampilkan In-App Floating Snackbar
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: const Color(0xFF1E293B),
          elevation: 6,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          content: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFF00AAAD).withAlpha(40),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.notifications_active_rounded, color: Color(0xFF00AAAD), size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      payload.title.isNotEmpty ? payload.title : 'Notifikasi NexSphere',
                      style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 13),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      payload.message,
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
          action: payload.taskId != null
              ? SnackBarAction(
                  label: 'Buka',
                  textColor: const Color(0xFF00AAAD),
                  onPressed: () {
                    TaskDetailModal.show(
                      context,
                      taskId: payload.taskId!,
                      kanbanRepo: widget.kanbanRepo,
                      currentUser: _currentUser,
                      onTaskUpdated: () {
                        if (_activeTab == 'my_tasks') _loadMyTasks();
                        if (_activeTab == 'timeline' && _timelineSpaceId != null) {
                          _loadTimeline(_timelineSpaceId!);
                        }
                      },
                    );
                  },
                )
              : null,
          duration: const Duration(seconds: 4),
        ),
      );
    });

    // 3. Listener saat notifikasi ditekan untuk membuka task
    _fcmTapSubscription = FcmService().onOpenedAppStream.listen((payload) {
      if (!mounted) return;
      if (payload.taskId != null) {
        TaskDetailModal.show(
          context,
          taskId: payload.taskId!,
          kanbanRepo: widget.kanbanRepo,
          currentUser: _currentUser,
          onTaskUpdated: () {
            if (_activeTab == 'my_tasks') _loadMyTasks();
            if (_activeTab == 'timeline' && _timelineSpaceId != null) {
              _loadTimeline(_timelineSpaceId!);
            }
          },
        );
      }
    });

  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _fetchUnreadNotificationsCount();
      if (_activeTab == 'my_tasks') _loadMyTasks();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _fcmMessageSubscription?.cancel();
    _fcmTapSubscription?.cancel();
    super.dispose();
  }

  Future<void> _fetchUnreadNotificationsCount() async {
    try {
      final notifs = await widget.kanbanRepo.getNotifications();
      if (!mounted) return;
      setState(() {
        _unreadNotificationsCount = notifs.where((n) => !n.isRead).length;
      });
    } catch (_) {}
  }

  Future<void> _loadInitialData() async {
    setState(() => _isLoading = true);
    try {
      final meRes = await widget.apiClient.dio.get(ApiEndpoints.me);
      _userName = meRes.data['user']?['name'];
      _userEmail = meRes.data['user']?['email'];
      final userJson = meRes.data['user'] as Map<String, dynamic>?;
      if (userJson != null) {
        _currentUser = UserModel.fromJson(userJson);
      }
      _fetchUnreadNotificationsCount();

      final wsRes = await widget.apiClient.dio.get(ApiEndpoints.workspaces);
      final wsList = wsRes.data['data'] as List<dynamic>? ?? [];

      if (mounted) {
        setState(() {
          _workspaces = wsList;
          if (wsList.isNotEmpty) {
            _selectedWorkspace = wsList.first as Map<String, dynamic>;
          }
        });
        if (_selectedWorkspace != null) {
          await _loadSpacesForWorkspace(_selectedWorkspace!['id'] as int);
        }
      }
      _loadDashboard();
      _loadMyTasks();
    } catch (_) {
      // Handle error gracefully
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _loadSpacesForWorkspace(int workspaceId) async {
    try {
      final res = await widget.apiClient.dio.get(ApiEndpoints.workspaceSpaces(workspaceId));
      if (mounted) {
        setState(() {
          _spaces = res.data['data'] as List<dynamic>? ?? [];
          if (_spaces.isNotEmpty && _timelineSpaceId == null) {
            _timelineSpaceId = _spaces.first['id'] as int;
          }
        });
        if (_activeTab == 'timeline' && _timelineSpaceId != null) {
          _loadTimeline(_timelineSpaceId!);
        }
      }
    } catch (_) {}
  }

  Future<void> _loadDashboard() async {
    setState(() => _isDashboardLoading = true);
    try {
      final res = await widget.apiClient.dio.get(ApiEndpoints.dashboard);
      if (mounted) {
        setState(() {
          _dashboardData = res.data['data'] as Map<String, dynamic>?;
        });
      }
    } catch (_) {} finally {
      if (mounted) setState(() => _isDashboardLoading = false);
    }
  }

  Future<void> _loadMyTasks() async {
    setState(() => _isMyTasksLoading = true);
    try {
      final res = await widget.apiClient.dio.get(ApiEndpoints.myTasks);
      if (mounted) {
        setState(() {
          _myTasks = res.data['data'] as List<dynamic>? ?? [];
        });
      }
    } catch (_) {} finally {
      if (mounted) setState(() => _isMyTasksLoading = false);
    }
  }

  Future<void> _loadActivityLogs() async {
    setState(() => _isLogsLoading = true);
    try {
      final res = await widget.apiClient.dio.get(ApiEndpoints.activityLogs);
      if (mounted) {
        setState(() {
          _activityLogs = res.data['data'] as List<dynamic>? ?? [];
        });
      }
    } catch (_) {} finally {
      if (mounted) setState(() => _isLogsLoading = false);
    }
  }

  Future<void> _loadTimeline(int spaceId) async {
    setState(() => _isTimelineLoading = true);
    try {
      final res = await widget.apiClient.dio.get(ApiEndpoints.spaceTimeline(spaceId));
      if (mounted) {
        setState(() {
          _timelineTasks = res.data['data'] as List<dynamic>? ?? [];
        });
      }
    } catch (_) {} finally {
      if (mounted) setState(() => _isTimelineLoading = false);
    }
  }

  void _switchTab(String tab, {bool closeDrawer = false}) {
    setState(() => _activeTab = tab);
    if (closeDrawer && Navigator.canPop(context)) {
      Navigator.pop(context);
    }
    if (tab == 'dashboard') {
      _loadDashboard();
      _loadMyTasks();
    } else if (tab == 'my_tasks') {
      _loadMyTasks();
    } else if (tab == 'activity_logs') {
      _loadActivityLogs();
    } else if (tab == 'timeline') {
      if (_timelineSpaceId != null) {
        _loadTimeline(_timelineSpaceId!);
      } else if (_spaces.isNotEmpty) {
        _timelineSpaceId = _spaces.first['id'] as int;
        _loadTimeline(_timelineSpaceId!);
      }
    }
  }

  void _showWorkspaceSwitcherSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade400,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Pilih Workspace', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  IconButton(
                    icon: const Icon(Icons.add_business_rounded, color: Color(0xFF00AAAD)),
                    tooltip: 'Tambah Workspace',
                    onPressed: () {
                      Navigator.pop(ctx);
                      _showCreateWorkspaceDialog();
                    },
                  ),
                ],
              ),
              if (_userName != null) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF00AAAD).withAlpha(15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: const Color(0xFF00AAAD),
                        radius: 16,
                        child: Text(
                          _userName!.isNotEmpty ? _userName![0].toUpperCase() : 'U',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(_userName!, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                            if (_userEmail != null)
                              Text(_userEmail!, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const Divider(),
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _workspaces.length,
                  itemBuilder: (context, idx) {
                    final ws = _workspaces[idx];
                    final isCurrent = _selectedWorkspace?['id'] == ws['id'];
                    return ListTile(
                      leading: CircleAvatar(
                        backgroundColor: isCurrent ? const Color(0xFF00AAAD) : Colors.grey.withAlpha(40),
                        child: Icon(
                          Icons.business_rounded,
                          color: isCurrent ? Colors.white : Colors.grey.shade700,
                          size: 18,
                        ),
                      ),
                      title: Text(
                        ws['name'] as String,
                        style: TextStyle(
                          fontWeight: isCurrent ? FontWeight.bold : FontWeight.w500,
                          color: isCurrent ? const Color(0xFF00AAAD) : null,
                        ),
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (isCurrent)
                            const Padding(
                              padding: EdgeInsets.only(right: 6),
                              child: Icon(Icons.check_rounded, color: Color(0xFF00AAAD), size: 20),
                            ),
                          PopupMenuButton<String>(
                            icon: const Icon(Icons.more_vert_rounded, size: 18, color: Colors.grey),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                            onSelected: (action) {
                              Navigator.pop(ctx);
                              if (action == 'edit') {
                                _showEditWorkspaceDialog(ws['id'] as int, ws['name'] as String);
                              } else if (action == 'delete') {
                                _confirmDeleteWorkspace(ws['id'] as int, ws['name'] as String);
                              }
                            },
                            itemBuilder: (popCtx) => [
                              const PopupMenuItem(
                                value: 'edit',
                                child: Row(
                                  children: [
                                    Icon(Icons.edit_outlined, size: 16),
                                    SizedBox(width: 8),
                                    Text('Ubah Nama', style: TextStyle(fontSize: 12)),
                                  ],
                                ),
                              ),
                              const PopupMenuItem(
                                value: 'delete',
                                child: Row(
                                  children: [
                                    Icon(Icons.delete_outline_rounded, size: 16, color: Colors.red),
                                    SizedBox(width: 8),
                                    Text('Hapus Workspace', style: TextStyle(fontSize: 12, color: Colors.red)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                      onTap: () {
                        setState(() => _selectedWorkspace = ws);
                        _loadSpacesForWorkspace(ws['id'] as int);
                        Navigator.pop(ctx);
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ==========================================
  // WORKSPACE CRUD DIALOGS
  // ==========================================
  void _showCreateWorkspaceDialog() {
    final nameCtrl = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Buat Workspace Baru'),
        content: TextField(
          controller: nameCtrl,
          decoration: const InputDecoration(
            labelText: 'Nama Workspace',
            hintText: 'Misal: Tim Digital Marketing',
          ),
          autofocus: true,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              final name = nameCtrl.text.trim();
              if (name.isEmpty) return;
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              try {
                await widget.apiClient.dio.post(ApiEndpoints.workspaces, data: {'name': name});
                await _loadInitialData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text("Workspace '$name' berhasil dibuat")),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gagal membuat workspace.')),
                  );
                }
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('Buat'),
          ),
        ],
      ),
    );
  }

  void _showEditWorkspaceDialog(int id, String currentName) {
    final nameCtrl = TextEditingController(text: currentName);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ubah Nama Workspace'),
        content: TextField(
          controller: nameCtrl,
          decoration: const InputDecoration(labelText: 'Nama Workspace'),
          autofocus: true,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              final name = nameCtrl.text.trim();
              if (name.isEmpty) return;
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              try {
                await widget.apiClient.dio.put(ApiEndpoints.workspaceDetail(id), data: {'name': name});
                await _loadInitialData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text("Workspace diubah menjadi '$name'")),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gagal memperbarui workspace.')),
                  );
                }
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  void _confirmDeleteWorkspace(int id, String name) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Workspace?'),
        content: Text("Yakin ingin menghapus workspace '$name'? Seluruh space dan tugas di dalamnya akan dihapus."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () async {
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              try {
                await widget.apiClient.dio.delete(ApiEndpoints.workspaceDetail(id));
                await _loadInitialData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text("Workspace '$name' berhasil dihapus")),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gagal menghapus workspace.')),
                  );
                }
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // SPACE CRUD DIALOGS
  // ==========================================
  void _showCreateSpaceDialog() {
    if (_selectedWorkspace == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih Workspace terlebih dahulu.')),
      );
      return;
    }

    final nameCtrl = TextEditingController();
    final descCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Buat Space Proyek Baru'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Nama Space Proyek *'),
                autofocus: true,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: descCtrl,
                decoration: const InputDecoration(labelText: 'Deskripsi Singkat'),
                maxLines: 2,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              final name = nameCtrl.text.trim();
              if (name.isEmpty) return;
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              try {
                await widget.apiClient.dio.post(
                  ApiEndpoints.workspaceSpaces(_selectedWorkspace!['id'] as int),
                  data: {
                    'name': name,
                    'description': descCtrl.text.trim(),
                  },
                );
                await _loadSpacesForWorkspace(_selectedWorkspace!['id'] as int);
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text("Space '$name' berhasil dibuat")),
                  );
                }
              } catch (_) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gagal membuat space.')),
                  );
                }
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('Buat Space'),
          ),
        ],
      ),
    );
  }

  void _showEditSpaceDialog(Map<String, dynamic> sp) {
    final nameCtrl = TextEditingController(text: sp['name'] as String?);
    final descCtrl = TextEditingController(text: sp['description'] as String? ?? '');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ubah Space Proyek'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Nama Space *'),
                autofocus: true,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: descCtrl,
                decoration: const InputDecoration(labelText: 'Deskripsi Singkat'),
                maxLines: 2,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              final name = nameCtrl.text.trim();
              if (name.isEmpty) return;
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              try {
                await widget.apiClient.dio.put(
                  ApiEndpoints.spaceDetail(sp['id'] as int),
                  data: {
                    'name': name,
                    'description': descCtrl.text.trim(),
                  },
                );
                await _loadSpacesForWorkspace(_selectedWorkspace!['id'] as int);
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text("Space '$name' berhasil diperbarui")),
                  );
                }
              } catch (_) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gagal memperbarui space.')),
                  );
                }
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  void _confirmDeleteSpace(int spaceId, String name) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Space?'),
        content: Text("Yakin ingin menghapus space '$name'? Seluruh task di dalamnya akan dihapus."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () async {
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              try {
                await widget.apiClient.dio.delete(ApiEndpoints.spaceDetail(spaceId));
                await _loadSpacesForWorkspace(_selectedWorkspace!['id'] as int);
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text("Space '$name' berhasil dihapus")),
                  );
                }
              } catch (_) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gagal menghapus space.')),
                  );
                }
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
  }

  Future<void> _logout() async {
    final currentToken = FcmService().currentToken;
    if (currentToken != null) {
      await FcmService().unregisterDeviceToken(widget.apiClient, currentToken);
    }
    await widget.apiClient.clearToken();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => LoginScreen(
          apiClient: widget.apiClient,
          kanbanRepo: widget.kanbanRepo,
        ),
      ),
    );
  }

  Color _priorityColor(String? priority) {
    switch (priority?.toLowerCase()) {
      case 'urgent':
        return Colors.purple;
      case 'high':
        return Colors.red;
      case 'medium':
        return Colors.amber.shade800;
      case 'low':
      default:
        return Colors.green;
    }
  }

  String _getAppTitle() {
    switch (_activeTab) {
      case 'dashboard':
        return 'Dashboard Ringkasan';
      case 'timeline':
        return 'Timeline Proyek';
      case 'my_tasks':
        return 'Tugas Saya (My Tasks)';
      case 'activity_logs':
        return 'Activity Logs';
      case 'spaces':
      default:
        return 'Spaces & Projects';
    }
  }

  Widget _buildNavItem({
    required String id,
    required String label,
    required IconData icon,
    required IconData activeIcon,
    String? badge,
  }) {
    final isActive = _activeTab == id;
    const activeColor = Color(0xFF00AAAD);

    return InkWell(
      onTap: () => _switchTab(id),
      borderRadius: BorderRadius.circular(12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: isActive ? activeColor.withAlpha(20) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(
                  isActive ? activeIcon : icon,
                  size: 22,
                  color: isActive ? activeColor : Colors.grey.shade600,
                ),
                if (badge != null && badge != '0')
                  Positioned(
                    top: -4,
                    right: -8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                      decoration: BoxDecoration(
                        color: isActive ? activeColor : Colors.grey.shade600,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      constraints: const BoxConstraints(minWidth: 14, minHeight: 14),
                      child: Text(
                        badge,
                        style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 3),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
                color: isActive ? activeColor : Colors.grey.shade600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        elevation: 0,
        title: Row(
          children: [
            InkWell(
              onTap: _showWorkspaceSwitcherSheet,
              borderRadius: BorderRadius.circular(20),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFF00AAAD).withAlpha(18),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFF00AAAD).withAlpha(45)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircleAvatar(
                      radius: 11,
                      backgroundColor: const Color(0xFF00AAAD),
                      child: Text(
                        (_selectedWorkspace?['name'] as String?)?.isNotEmpty ?? false
                            ? (_selectedWorkspace!['name'] as String)[0].toUpperCase()
                            : 'W',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 11,
                        ),
                      ),
                    ),
                    const SizedBox(width: 7),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 120),
                      child: Text(
                        _selectedWorkspace?['name'] as String? ?? 'Workspace',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF00AAAD),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 3),
                    const Icon(Icons.keyboard_arrow_down_rounded, size: 16, color: Color(0xFF00AAAD)),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                _getAppTitle(),
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.end,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Stack(
              clipBehavior: Clip.none,
              children: [
                const Icon(Icons.notifications_outlined, size: 23),
                if (_unreadNotificationsCount > 0)
                  Positioned(
                    top: -2,
                    right: -2,
                    child: Container(
                      padding: const EdgeInsets.all(3),
                      decoration: const BoxDecoration(
                        color: Colors.redAccent,
                        shape: BoxShape.circle,
                      ),
                      constraints: const BoxConstraints(
                        minWidth: 16,
                        minHeight: 16,
                      ),
                      child: Text(
                        _unreadNotificationsCount > 9 ? '9+' : '$_unreadNotificationsCount',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.bold,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
              ],
            ),
            tooltip: 'Notifikasi',
            onPressed: () {
              NotificationsSheet.show(
                context,
                kanbanRepo: widget.kanbanRepo,
                onNotificationsUpdated: _fetchUnreadNotificationsCount,
                onNotificationTapped: (notification) {
                  if (notification.taskId != null) {
                    TaskDetailModal.show(
                      context,
                      taskId: notification.taskId!,
                      kanbanRepo: widget.kanbanRepo,
                      currentUser: _currentUser,
                      onTaskUpdated: () {
                        if (_activeTab == 'my_tasks') _loadMyTasks();
                        if (_activeTab == 'timeline' && _timelineSpaceId != null) {
                          _loadTimeline(_timelineSpaceId!);
                        }
                      },
                    );
                  }
                },
              );
            },
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert_rounded),
            onSelected: (val) {
              if (val == 'switch_workspace') {
                _showWorkspaceSwitcherSheet();
              } else if (val == 'refresh') {
                _loadInitialData();
              } else if (val == 'logout') {
                _logout();
              }
            },
            itemBuilder: (ctx) => [
              const PopupMenuItem(
                value: 'switch_workspace',
                child: Row(
                  children: [
                    Icon(Icons.swap_horiz_rounded, size: 18, color: Color(0xFF00AAAD)),
                    SizedBox(width: 8),
                    Text('Ganti Workspace', style: TextStyle(fontSize: 13)),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'refresh',
                child: Row(
                  children: [
                    Icon(Icons.refresh_rounded, size: 18, color: Colors.grey),
                    SizedBox(width: 8),
                    Text('Segarkan Data', style: TextStyle(fontSize: 13)),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    Icon(Icons.logout_rounded, size: 18, color: Colors.red),
                    SizedBox(width: 8),
                    Text('Keluar (Logout)', style: TextStyle(fontSize: 13, color: Colors.red)),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      floatingActionButton: _activeTab == 'spaces'
          ? FloatingActionButton.extended(
              onPressed: _showCreateSpaceDialog,
              backgroundColor: const Color(0xFF00AAAD),
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Buat Space'),
            )
          : null,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withAlpha(10),
              blurRadius: 10,
              offset: const Offset(0, -2),
            ),
          ],
          border: Border(
            top: BorderSide(color: Colors.grey.withAlpha(30)),
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(
                  id: 'dashboard',
                  label: 'Home',
                  icon: Icons.dashboard_outlined,
                  activeIcon: Icons.dashboard_rounded,
                ),
                _buildNavItem(
                  id: 'spaces',
                  label: 'Spaces',
                  icon: Icons.folder_outlined,
                  activeIcon: Icons.folder_rounded,
                  badge: _spaces.isNotEmpty ? '${_spaces.length}' : null,
                ),
                _buildNavItem(
                  id: 'my_tasks',
                  label: 'Tugas Saya',
                  icon: Icons.check_circle_outline_rounded,
                  activeIcon: Icons.check_circle_rounded,
                  badge: _myTasks.isNotEmpty
                      ? '${_myTasks.where((t) => !(t['stage']?['is_done'] ?? false)).length}'
                      : null,
                ),
                _buildNavItem(
                  id: 'timeline',
                  label: 'Timeline',
                  icon: Icons.view_timeline_outlined,
                  activeIcon: Icons.view_timeline_rounded,
                ),
                _buildNavItem(
                  id: 'activity_logs',
                  label: 'Aktivitas',
                  icon: Icons.history_rounded,
                  activeIcon: Icons.history_rounded,
                ),
              ],
            ),
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _buildCurrentContent(),
    );
  }

  Widget _buildCurrentContent() {
    switch (_activeTab) {
      case 'dashboard':
        return _buildDashboardView();
      case 'timeline':
        return _buildTimelineView();
      case 'my_tasks':
        return _buildMyTasksView();
      case 'activity_logs':
        return _buildActivityLogsView();
      case 'spaces':
      default:
        return _buildSpacesView();
    }
  }

  // 1. DASHBOARD VIEW (MODERN PERSONAL FOCUS + CAROUSEL)
  Widget _buildDashboardView() {
    if (_isDashboardLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final d = _dashboardData ?? {};
    final totalWs = d['totalWorkspaces'] ?? _workspaces.length;
    final totalSp = d['totalSpaces'] ?? _spaces.length;
    final totalTk = d['totalTasks'] ?? 0;
    final compTk = d['completedTasks'] ?? 0;
    final actTk = d['activeTasks'] ?? 0;
    final rate = d['completionRate'] ?? 0;

    final activeTasks = _myTasks.where((t) => !(t['stage']?['is_done'] ?? false)).toList();

    return RefreshIndicator(
      onRefresh: () async {
        await _loadDashboard();
        await _loadMyTasks();
      },
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        children: [
          // Greeting Header
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Halo, ${_userName ?? 'Rekan'} 👋',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 19),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Berikut ringkasan fokus dan progres tugasmu:',
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Completion Rate Gradient Card
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF00AAAD), Color(0xFF007A7C)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(18),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF00AAAD).withAlpha(50),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Tingkat Penyelesaian Proyek',
                      style: TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w500),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '$compTk / $totalTk Selesai',
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Text(
                      '$rate%',
                      style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      rate >= 75
                          ? 'Hampir Tuntas 🚀'
                          : (rate >= 40 ? 'Progres Stabil ⚡' : 'Baru Dimulai 🎯'),
                      style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: totalTk > 0 ? compTk / totalTk : 0,
                    backgroundColor: Colors.white24,
                    valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                    minHeight: 7,
                  ),
                ),
              ],
            ),
          ),

          // Focus: My Tasks (Up Next)
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.bolt_rounded, size: 18, color: Colors.amber),
                  SizedBox(width: 6),
                  Text(
                    'Fokus Tugas Saya (Up Next)',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => _switchTab('my_tasks'),
                style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
                child: const Text('Buka Semua', style: TextStyle(fontSize: 12, color: Color(0xFF00AAAD))),
              ),
            ],
          ),
          const SizedBox(height: 4),
          if (activeTasks.isEmpty)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: Colors.grey.withAlpha(30)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: Colors.green.withAlpha(25),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.done_all_rounded, color: Colors.green, size: 20),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Semua Tugas Beres! 🎉', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        SizedBox(height: 2),
                        Text('Tidak ada tugas pending untuk akunmu saat ini.', style: TextStyle(fontSize: 11, color: Colors.grey)),
                      ],
                    ),
                  ),
                ],
              ),
            )
          else
            ...activeTasks.take(2).map((t) {
              final priority = (t['priority'] as String?) ?? 'medium';
              final pColor = _priorityColor(priority);
              return Card(
                elevation: 0.5,
                margin: const EdgeInsets.only(bottom: 8),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: BorderSide(color: Colors.grey.withAlpha(30)),
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () {
                    TaskDetailModal.show(
                      context,
                      taskId: t['id'] as int,
                      kanbanRepo: widget.kanbanRepo,
                      currentUser: _currentUser,
                      onTaskUpdated: () {
                        _loadDashboard();
                        _loadMyTasks();
                      },
                    );
                  },
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    child: Row(
                      children: [
                        Container(
                          width: 4,
                          height: 36,
                          decoration: BoxDecoration(
                            color: pColor,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                t['title'] as String,
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                    decoration: BoxDecoration(
                                      color: pColor.withAlpha(20),
                                      borderRadius: BorderRadius.circular(5),
                                    ),
                                    child: Text(
                                      priority.toUpperCase(),
                                      style: TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: pColor),
                                    ),
                                  ),
                                  if (t['due_at'] != null) ...[
                                    const SizedBox(width: 8),
                                    Icon(Icons.calendar_today_outlined, size: 11, color: Colors.grey.shade600),
                                    const SizedBox(width: 4),
                                    Text(
                                      t['due_at'] as String,
                                      style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                    ),
                                  ],
                                ],
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: Colors.grey),
                      ],
                    ),
                  ),
                ),
              );
            }),

          // Spaces Horizontal Carousel
          const SizedBox(height: 18),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Proyek Aktif (Spaces)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              TextButton(
                onPressed: () => _switchTab('spaces'),
                style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
                child: const Text('Buka Spaces', style: TextStyle(fontSize: 12, color: Color(0xFF00AAAD))),
              ),
            ],
          ),
          const SizedBox(height: 6),
          SizedBox(
            height: 105,
            child: _spaces.isEmpty
                ? Center(
                    child: Text(
                      'Belum ada space proyek aktif.',
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
                    ),
                  )
                : ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: _spaces.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 10),
                    itemBuilder: (ctx, idx) {
                      final sp = _spaces[idx];
                      final name = sp['name'] as String;
                      return InkWell(
                        borderRadius: BorderRadius.circular(16),
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => KanbanBoardScreen(
                                spaceId: sp['id'] as int,
                                spaceName: name,
                                kanbanRepo: widget.kanbanRepo,
                              ),
                            ),
                          );
                        },
                        child: Container(
                          width: 155,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: Colors.grey.withAlpha(35)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Container(
                                    width: 32,
                                    height: 32,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF00AAAD).withAlpha(25),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: const Icon(Icons.folder_rounded, color: Color(0xFF00AAAD), size: 18),
                                  ),
                                  const Icon(Icons.arrow_forward_rounded, size: 14, color: Colors.grey),
                                ],
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    name,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    'Buka Kanban',
                                    style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),

          // Stat Cards Grid
          const SizedBox(height: 22),
          const Text('Metrik Ringkasan:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          const SizedBox(height: 10),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: 1.45,
            children: [
              _buildStatCard('Total Workspaces', '$totalWs', Icons.business_rounded, const Color(0xFF00AAAD)),
              _buildStatCard('Total Spaces', '$totalSp', Icons.folder_open_rounded, Colors.indigo),
              _buildStatCard('Tugas Aktif', '$actTk', Icons.pending_actions_rounded, Colors.orange),
              _buildStatCard('Tugas Selesai', '$compTk', Icons.check_circle_rounded, Colors.green),
            ],
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildStatCard(String label, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.withAlpha(35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey, fontWeight: FontWeight.w500)),
              Icon(icon, size: 20, color: color),
            ],
          ),
          Text(
            value,
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: color),
          ),
        ],
      ),
    );
  }

  // 2. SPACES & PROJECTS VIEW WITH FULL CRUD
  Widget _buildSpacesView() {
    return RefreshIndicator(
      onRefresh: _loadInitialData,
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        children: [
          // Active Workspace Banner
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.grey.withAlpha(30)),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF00AAAD).withAlpha(20),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.business_rounded, size: 16, color: Color(0xFF00AAAD)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Workspace Aktif', style: TextStyle(fontSize: 10, color: Colors.grey, fontWeight: FontWeight.w500)),
                      Text(
                        _selectedWorkspace?['name'] as String? ?? 'Workspace',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                TextButton(
                  onPressed: _showWorkspaceSwitcherSheet,
                  style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
                  child: const Text('Ganti', style: TextStyle(fontSize: 12, color: Color(0xFF00AAAD), fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Daftar Space Proyek (${_spaces.length})',
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
              ),
              ElevatedButton.icon(
                onPressed: _showCreateSpaceDialog,
                icon: const Icon(Icons.add_rounded, size: 16),
                label: const Text('Tambah Space', style: TextStyle(fontSize: 12)),
                style: ElevatedButton.styleFrom(
                  visualDensity: VisualDensity.compact,
                  backgroundColor: const Color(0xFF00AAAD),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (_spaces.isEmpty)
            Container(
              padding: const EdgeInsets.all(28),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.grey.withAlpha(30)),
              ),
              child: Column(
                children: [
                  const Icon(Icons.folder_open_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 8),
                  const Text('Belum ada Space proyek di workspace ini.', style: TextStyle(color: Colors.grey, fontSize: 13)),
                  const SizedBox(height: 14),
                  ElevatedButton.icon(
                    onPressed: _showCreateSpaceDialog,
                    icon: const Icon(Icons.add),
                    label: const Text('Buat Space Pertama'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF00AAAD),
                      foregroundColor: Colors.white,
                    ),
                  ),
                ],
              ),
            )
          else
            ..._spaces.map((sp) {
              return Card(
                elevation: 0.5,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: BorderSide(color: Colors.grey.withAlpha(35)),
                ),
                margin: const EdgeInsets.only(bottom: 12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => KanbanBoardScreen(
                          spaceId: sp['id'] as int,
                          spaceName: sp['name'] as String,
                          kanbanRepo: widget.kanbanRepo,
                        ),
                      ),
                    );
                  },
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: const Color(0xFF00AAAD).withAlpha(25),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(Icons.view_kanban_rounded, color: Color(0xFF00AAAD), size: 22),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      sp['name'] as String,
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.green.withAlpha(20),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Text(
                                      'Aktif',
                                      style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Colors.green),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 3),
                              Text(
                                sp['description'] as String? ?? 'Space proyek tim',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                              ),
                            ],
                          ),
                        ),
                        PopupMenuButton<String>(
                          icon: const Icon(Icons.more_vert_rounded, size: 20, color: Colors.grey),
                          onSelected: (val) {
                            if (val == 'kanban') {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => KanbanBoardScreen(
                                    spaceId: sp['id'] as int,
                                    spaceName: sp['name'] as String,
                                    kanbanRepo: widget.kanbanRepo,
                                  ),
                                ),
                              );
                            } else if (val == 'timeline') {
                              setState(() {
                                _timelineSpaceId = sp['id'] as int;
                                _activeTab = 'timeline';
                              });
                              _loadTimeline(sp['id'] as int);
                            } else if (val == 'edit') {
                              _showEditSpaceDialog(sp as Map<String, dynamic>);
                            } else if (val == 'delete') {
                              _confirmDeleteSpace(sp['id'] as int, sp['name'] as String);
                            }
                          },
                          itemBuilder: (ctx) => [
                            const PopupMenuItem(
                              value: 'kanban',
                              child: Row(
                                children: [
                                  Icon(Icons.view_kanban_rounded, size: 18),
                                  SizedBox(width: 8),
                                  Text('Papan Kanban'),
                                ],
                              ),
                            ),
                            const PopupMenuItem(
                              value: 'timeline',
                              child: Row(
                                children: [
                                  Icon(Icons.view_timeline_rounded, size: 18),
                                  SizedBox(width: 8),
                                  Text('Timeline Proyek'),
                                ],
                              ),
                            ),
                            const PopupMenuItem(
                              value: 'edit',
                              child: Row(
                                children: [
                                  Icon(Icons.edit_outlined, size: 18),
                                  SizedBox(width: 8),
                                  Text('Ubah Space'),
                                ],
                              ),
                            ),
                            const PopupMenuItem(
                              value: 'delete',
                              child: Row(
                                children: [
                                  Icon(Icons.delete_outline_rounded, size: 18, color: Colors.red),
                                  SizedBox(width: 8),
                                  Text('Hapus Space', style: TextStyle(color: Colors.red)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }

  // 3. TIMELINE VIEW
  Widget _buildTimelineView() {
    if (_isTimelineLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        if (_spaces.isNotEmpty)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              border: Border(bottom: BorderSide(color: Colors.grey.withAlpha(30))),
            ),
            child: Row(
              children: [
                const Icon(Icons.folder_rounded, size: 18, color: Color(0xFF00AAAD)),
                const SizedBox(width: 8),
                const Text('Space:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButton<int>(
                    value: _timelineSpaceId,
                    isExpanded: true,
                    underline: const SizedBox(),
                    items: _spaces.map((sp) {
                      return DropdownMenuItem<int>(
                        value: sp['id'] as int,
                        child: Text(sp['name'] as String, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                      );
                    }).toList(),
                    onChanged: (id) {
                      if (id != null) {
                        setState(() => _timelineSpaceId = id);
                        _loadTimeline(id);
                      }
                    },
                  ),
                ),
              ],
            ),
          ),
        Expanded(
          child: _timelineTasks.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.calendar_month_rounded, size: 56, color: Colors.grey),
                      SizedBox(height: 12),
                      Text('Belum ada tugas di Timeline ini', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      SizedBox(height: 6),
                      Text('Buat tugas dengan due date di Papan Kanban untuk melihat timeline.', style: TextStyle(color: Colors.grey, fontSize: 12)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () async {
                    if (_timelineSpaceId != null) await _loadTimeline(_timelineSpaceId!);
                  },
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _timelineTasks.length,
                    itemBuilder: (ctx, idx) {
                      final t = _timelineTasks[idx];
                      final stage = t['stage'] as Map<String, dynamic>? ?? {};
                      final isDone = stage['is_done'] as bool? ?? false;
                      final priority = (t['priority'] as String?) ?? 'medium';
                      final pColor = _priorityColor(priority);
                      final dueAt = t['due_at'] as String?;

                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Date pill on left
                            SizedBox(
                              width: 80,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    dueAt ?? 'No Date',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.bold,
                                      color: isDone ? Colors.green : (dueAt == null ? Colors.grey : Colors.indigo),
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: (isDone ? Colors.green : Colors.grey).withAlpha(20),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      isDone ? 'Selesai' : 'Aktif',
                                      style: TextStyle(
                                        fontSize: 9,
                                        fontWeight: FontWeight.bold,
                                        color: isDone ? Colors.green : Colors.grey,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 10),
                            // Vertical Timeline line
                            Column(
                              children: [
                                Container(
                                  width: 12,
                                  height: 12,
                                  decoration: BoxDecoration(
                                    color: isDone ? Colors.green : const Color(0xFF00AAAD),
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 2),
                                  ),
                                ),
                                Container(
                                  width: 2,
                                  height: 50,
                                  color: Colors.grey.withAlpha(50),
                                ),
                              ],
                            ),
                            const SizedBox(width: 10),
                            // Task Card
                            Expanded(
                              child: Card(
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  side: BorderSide(color: Colors.grey.withAlpha(35)),
                                ),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(12),
                                  onTap: () {
                                    final taskId = t['id'] as int;
                                    TaskDetailModal.show(
                                      context,
                                      taskId: taskId,
                                      kanbanRepo: widget.kanbanRepo,
                                      currentUser: _currentUser,
                                      onTaskUpdated: () {
                                        if (_timelineSpaceId != null) {
                                          _loadTimeline(_timelineSpaceId!);
                                        }
                                      },
                                    );
                                  },
                                  child: Padding(
                                    padding: const EdgeInsets.all(12),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                          children: [
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                              decoration: BoxDecoration(
                                                color: pColor.withAlpha(25),
                                                borderRadius: BorderRadius.circular(6),
                                              ),
                                              child: Text(
                                                priority.toUpperCase(),
                                                style: TextStyle(color: pColor, fontSize: 9, fontWeight: FontWeight.bold),
                                              ),
                                            ),
                                            Text(
                                              stage['name'] as String? ?? 'Tahapan',
                                              style: const TextStyle(fontSize: 10, color: Colors.grey),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          t['title'] as String,
                                          style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.bold,
                                            decoration: isDone ? TextDecoration.lineThrough : null,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }

  // 4. MY TASKS VIEW WITH FILTER
  Widget _buildMyTasksView() {
    if (_isMyTasksLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final totalCount = _myTasks.length;
    final activeCount = _myTasks.where((t) => !(t['stage']?['is_done'] ?? false)).length;
    final completedCount = _myTasks.where((t) => (t['stage']?['is_done'] ?? false)).length;

    final filtered = _myTasks.where((t) {
      final isDone = t['stage']?['is_done'] as bool? ?? false;
      if (_myTasksFilter == 'completed') return isDone;
      if (_myTasksFilter == 'active') return !isDone;
      return true;
    }).toList();

    return Column(
      children: [
        // Filter Chips Bar
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            border: Border(bottom: BorderSide(color: Colors.grey.withAlpha(30))),
          ),
          child: Row(
            children: [
              ChoiceChip(
                label: Text('Semua ($totalCount)', style: const TextStyle(fontSize: 11)),
                selected: _myTasksFilter == 'all',
                selectedColor: const Color(0xFF00AAAD),
                labelStyle: TextStyle(
                  color: _myTasksFilter == 'all' ? Colors.white : null,
                  fontWeight: _myTasksFilter == 'all' ? FontWeight.bold : null,
                ),
                onSelected: (_) => setState(() => _myTasksFilter = 'all'),
              ),
              const SizedBox(width: 8),
              ChoiceChip(
                label: Text('Aktif ($activeCount)', style: const TextStyle(fontSize: 11)),
                selected: _myTasksFilter == 'active',
                selectedColor: const Color(0xFF00AAAD),
                labelStyle: TextStyle(
                  color: _myTasksFilter == 'active' ? Colors.white : null,
                  fontWeight: _myTasksFilter == 'active' ? FontWeight.bold : null,
                ),
                onSelected: (_) => setState(() => _myTasksFilter = 'active'),
              ),
              const SizedBox(width: 8),
              ChoiceChip(
                label: Text('Selesai ($completedCount)', style: const TextStyle(fontSize: 11)),
                selected: _myTasksFilter == 'completed',
                selectedColor: const Color(0xFF00AAAD),
                labelStyle: TextStyle(
                  color: _myTasksFilter == 'completed' ? Colors.white : null,
                  fontWeight: _myTasksFilter == 'completed' ? FontWeight.bold : null,
                ),
                onSelected: (_) => setState(() => _myTasksFilter = 'completed'),
              ),
            ],
          ),
        ),
        Expanded(
          child: filtered.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.assignment_turned_in_rounded, size: 56, color: Colors.grey),
                      const SizedBox(height: 12),
                      const Text('Tidak ada tugas dalam kategori ini', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      const SizedBox(height: 16),
                      ElevatedButton.icon(
                        onPressed: _loadMyTasks,
                        icon: const Icon(Icons.refresh_rounded, size: 16),
                        label: const Text('Muat Ulang'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF00AAAD),
                          foregroundColor: Colors.white,
                        ),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadMyTasks,
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    itemCount: filtered.length,
                    itemBuilder: (ctx, idx) {
                      final t = filtered[idx];
                      final priority = (t['priority'] as String?) ?? 'medium';
                      final pColor = _priorityColor(priority);
                      final stageName = t['stage']?['name'] as String? ?? 'Tahapan';
                      final isDone = t['stage']?['is_done'] as bool? ?? false;
                      final taskId = t['id'] as int;

                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        elevation: 0.5,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                          side: BorderSide(color: Colors.grey.withAlpha(35)),
                        ),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () {
                            TaskDetailModal.show(
                              context,
                              taskId: taskId,
                              kanbanRepo: widget.kanbanRepo,
                              currentUser: _currentUser,
                              onTaskUpdated: _loadMyTasks,
                            );
                          },
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Padding(
                                  padding: const EdgeInsets.only(top: 2, right: 10),
                                  child: Icon(
                                    isDone ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                                    color: isDone ? Colors.green : Colors.grey.shade400,
                                    size: 20,
                                  ),
                                ),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: pColor.withAlpha(25),
                                              borderRadius: BorderRadius.circular(5),
                                            ),
                                            child: Text(
                                              priority.toUpperCase(),
                                              style: TextStyle(color: pColor, fontSize: 9, fontWeight: FontWeight.bold),
                                            ),
                                          ),
                                          const SizedBox(width: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: (isDone ? Colors.green : Colors.grey).withAlpha(20),
                                              borderRadius: BorderRadius.circular(5),
                                            ),
                                            child: Text(
                                              stageName,
                                              style: TextStyle(
                                                fontSize: 9,
                                                fontWeight: FontWeight.w600,
                                                color: isDone ? Colors.green : null,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 6),
                                      Text(
                                        t['title'] as String,
                                        style: TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.bold,
                                          decoration: isDone ? TextDecoration.lineThrough : null,
                                          color: isDone ? Colors.grey : null,
                                        ),
                                      ),
                                      if (t['description'] != null && (t['description'] as String).isNotEmpty) ...[
                                        const SizedBox(height: 3),
                                        Text(
                                          t['description'] as String,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(fontSize: 11, color: Colors.grey),
                                        ),
                                      ],
                                      if (t['due_at'] != null || (t['checklists_count'] != null && (t['checklists_count'] as int) > 0)) ...[
                                        const SizedBox(height: 8),
                                        Row(
                                          children: [
                                            if (t['checklists_count'] != null && (t['checklists_count'] as int) > 0) ...[
                                              const Icon(Icons.check_box_outlined, size: 12, color: Colors.grey),
                                              const SizedBox(width: 3),
                                              Text(
                                                '${t['completed_checklists_count'] ?? 0}/${t['checklists_count']}',
                                                style: const TextStyle(fontSize: 10, color: Colors.grey),
                                              ),
                                              const SizedBox(width: 10),
                                            ],
                                            if (t['due_at'] != null) ...[
                                              const Icon(Icons.calendar_today_outlined, size: 11, color: Colors.grey),
                                              const SizedBox(width: 3),
                                              Text(
                                                t['due_at'] as String,
                                                style: const TextStyle(fontSize: 10, color: Colors.grey),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                const Icon(Icons.chevron_right_rounded, size: 16, color: Colors.grey),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }

  // 5. ACTIVITY LOGS VIEW
  Widget _buildActivityLogsView() {
    if (_isLogsLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_activityLogs.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.history_rounded, size: 56, color: Colors.grey),
            const SizedBox(height: 12),
            const Text('Belum ada log aktivitas', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: _loadActivityLogs,
              icon: const Icon(Icons.refresh_rounded, size: 16),
              label: const Text('Muat Ulang'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF00AAAD),
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      );
    }

    final filteredLogs = _activityLogs.where((log) {
      if (_activityLogFilter == 'all') return true;
      final action = (log['action'] as String? ?? '').toLowerCase();
      if (_activityLogFilter == 'task') {
        return action.contains('task') || action.contains('timeline') || action.contains('dependency');
      }
      if (_activityLogFilter == 'comments') {
        return action.contains('comment');
      }
      if (_activityLogFilter == 'files') {
        return action.contains('attachment') || action.contains('file');
      }
      if (_activityLogFilter == 'checklists') {
        return action.contains('checklist');
      }
      return true;
    }).toList();

    return Column(
      children: [
        // Category Filter Chips
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            children: [
              _buildLogFilterChip('all', 'Semua (${_activityLogs.length})'),
              const SizedBox(width: 6),
              _buildLogFilterChip('task', '🚀 Tugas'),
              const SizedBox(width: 6),
              _buildLogFilterChip('comments', '💬 Komentar'),
              const SizedBox(width: 6),
              _buildLogFilterChip('files', '📎 Berkas'),
              const SizedBox(width: 6),
              _buildLogFilterChip('checklists', '✅ Checklist'),
            ],
          ),
        ),
        const Divider(height: 1),
        // Filtered List
        Expanded(
          child: RefreshIndicator(
            onRefresh: _loadActivityLogs,
            child: filteredLogs.isEmpty
                ? const Center(
                    child: Text(
                      'Tidak ada aktivitas dalam kategori ini',
                      style: TextStyle(color: Colors.grey, fontSize: 13),
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    itemCount: filteredLogs.length,
                    itemBuilder: (ctx, idx) {
                      final log = filteredLogs[idx];
                      final action = (log['action'] as String? ?? '').toLowerCase();

                      IconData iconData = Icons.bolt_rounded;
                      Color iconColor = const Color(0xFF00AAAD);
                      if (action.contains('comment')) {
                        iconData = Icons.chat_bubble_rounded;
                        iconColor = const Color(0xFF10B981);
                      } else if (action.contains('attachment') || action.contains('file')) {
                        iconData = Icons.attach_file_rounded;
                        iconColor = const Color(0xFFF59E0B);
                      } else if (action.contains('checklist')) {
                        iconData = Icons.check_circle_rounded;
                        iconColor = const Color(0xFF06B6D4);
                      } else if (action.contains('moved')) {
                        iconData = Icons.swap_horiz_rounded;
                        iconColor = const Color(0xFF6366F1);
                      }

                      return Card(
                        elevation: 0.5,
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                          side: BorderSide(color: Colors.grey.withAlpha(30)),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: iconColor.withAlpha(20),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Icon(iconData, size: 18, color: iconColor),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      log['description'] as String? ?? 'Aktivitas sistem',
                                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        Icon(Icons.access_time_rounded, size: 11, color: Colors.grey.shade500),
                                        const SizedBox(width: 4),
                                        Text(
                                          log['created_at'] as String? ?? '',
                                          style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }

  Widget _buildLogFilterChip(String filterKey, String label) {
    final isSelected = _activityLogFilter == filterKey;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) => setState(() => _activityLogFilter = filterKey),
      selectedColor: const Color(0xFF00AAAD),
      labelStyle: TextStyle(
        fontSize: 11,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        color: isSelected ? Colors.white : null,
      ),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      showCheckmark: false,
    );
  }
}

// ==========================================
// 4. KANBAN BOARD SCREEN WITH FULL TASK CRUD
// ==========================================
class KanbanBoardScreen extends StatefulWidget {
  final int spaceId;
  final String spaceName;
  final KanbanRepository kanbanRepo;

  const KanbanBoardScreen({
    super.key,
    required this.spaceId,
    required this.spaceName,
    required this.kanbanRepo,
  });

  @override
  State<KanbanBoardScreen> createState() => _KanbanBoardScreenState();
}

class _KanbanBoardScreenState extends State<KanbanBoardScreen> {
  bool _isLoading = true;
  List<StageModel> _stages = [];
  List<UserModel> _spaceMembers = [];
  late final PageController _pageController;
  int _currentPage = 0;

  ReverbWebSocketClient? _reverbClient;
  StreamSubscription<ReverbEvent>? _realtimeSubscription;
  StreamSubscription<String>? _connectionSubscription;
  String _wsStatus = ReverbConnectionState.disconnected;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(viewportFraction: 0.88);
    _fetchStages();
    _fetchSpaceMembers();
    _initRealtime();
  }

  @override
  void dispose() {
    _pageController.dispose();
    _realtimeSubscription?.cancel();
    _connectionSubscription?.cancel();
    _reverbClient?.disconnect();
    super.dispose();
  }

  void _initRealtime() {
    _reverbClient = ReverbWebSocketClient(
      apiClient: widget.kanbanRepo.apiClient,
    );

    _connectionSubscription =
        _reverbClient!.connectionStateStream.listen((state) {
      if (mounted) {
        setState(() => _wsStatus = state);
      }
    });

    _realtimeSubscription = _reverbClient!.eventStream.listen((event) {
      _handleRealtimeEvent(event);
    });

    _reverbClient!.connect();
    _reverbClient!.subscribe('space.${widget.spaceId}', isPrivate: true);
  }

  void _handleRealtimeEvent(ReverbEvent event) {
    final ev = event.event;
    if (ev == RealtimeEvents.taskCreated ||
        ev == RealtimeEvents.taskUpdated ||
        ev == RealtimeEvents.taskMoved ||
        ev == RealtimeEvents.taskDeleted ||
        ev == RealtimeEvents.taskCommentAdded ||
        ev == RealtimeEvents.taskChecklistToggled ||
        ev == RealtimeEvents.taskAttachmentUploaded ||
        ev == RealtimeEvents.taskAttachmentDeleted ||
        ev == RealtimeEvents.spaceStageChanged ||
        ev.contains('Task') ||
        ev.contains('Stage')) {
      _fetchStages(silent: true);
    }
  }

  Future<void> _fetchSpaceMembers() async {
    try {
      final res = await widget.kanbanRepo.apiClient.dio
          .get(ApiEndpoints.spaceDetail(widget.spaceId));
      final membersJson = res.data['data']?['members'] as List<dynamic>? ?? [];
      if (mounted) {
        setState(() {
          _spaceMembers = membersJson
              .map((m) => UserModel.fromJson(m as Map<String, dynamic>))
              .toList();
        });
      }
    } catch (_) {}
  }

  Future<void> _fetchStages({bool silent = false}) async {
    if (!silent) setState(() => _isLoading = true);
    try {
      final stages = await widget.kanbanRepo.getSpaceStages(widget.spaceId);
      if (mounted) {
        setState(() {
          _stages = stages;
        });
      }
    } catch (_) {
      // Handle error
    } finally {
      if (mounted && !silent) setState(() => _isLoading = false);
    }
  }

  void _openTaskDetail(TaskModel task) {
    TaskDetailModal.show(
      context,
      taskId: task.id,
      initialTask: task,
      kanbanRepo: widget.kanbanRepo,
      stages: _stages,
      spaceMembers: _spaceMembers,
      onTaskUpdated: () => _fetchStages(silent: true),
    );
  }

  Future<void> _moveTaskQuick(TaskModel task, int targetStageId) async {
    if (task.stageId == targetStageId) return;
    try {
      await widget.kanbanRepo.moveTask(
        taskId: task.id,
        stageId: targetStageId,
        position: 1,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Status tugas berhasil diperbarui'),
            duration: Duration(seconds: 2),
          ),
        );
      }
      await _fetchStages(silent: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal memindahkan tugas: $e')),
        );
      }
    }
  }

  void _showSpaceMembersSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade400,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Anggota Space (${_spaceMembers.length})',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, size: 20),
                    onPressed: () => Navigator.pop(ctx),
                  ),
                ],
              ),
              const Divider(),
              if (_spaceMembers.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(
                    child: Text(
                      'Tidak ada anggota khusus di space ini (meminjam role workspace).',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey, fontSize: 13),
                    ),
                  ),
                )
              else
                Flexible(
                  child: ListView.builder(
                    shrinkWrap: true,
                    itemCount: _spaceMembers.length,
                    itemBuilder: (ctx, idx) {
                      final member = _spaceMembers[idx];
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor: const Color(0xFF00AAAD).withAlpha(40),
                          child: Text(
                            member.name.isNotEmpty ? member.name[0].toUpperCase() : '?',
                            style: const TextStyle(
                              color: Color(0xFF00AAAD),
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        title: Text(member.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: Text(member.email, style: const TextStyle(fontSize: 12)),
                        trailing: member.role != null
                            ? Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: Colors.grey.withAlpha(30),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  member.role!,
                                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                                ),
                              )
                            : null,
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Color _parseColor(String? hexString) {
    if (hexString == null || hexString.isEmpty) return const Color(0xFF64748B);
    try {
      final buffer = StringBuffer();
      if (hexString.length == 6 || hexString.length == 7) buffer.write('ff');
      buffer.write(hexString.replaceFirst('#', ''));
      return Color(int.parse(buffer.toString(), radix: 16));
    } catch (_) {
      return const Color(0xFF64748B);
    }
  }

  Color _priorityColor(String priority) {
    switch (priority.toLowerCase()) {
      case 'urgent':
        return Colors.purple;
      case 'high':
        return Colors.red;
      case 'medium':
        return Colors.amber.shade800;
      case 'low':
      default:
        return Colors.green;
    }
  }

  void _showCreateTaskDialog({int? preselectedStageId}) {
    if (_stages.isEmpty) return;

    final titleCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    int selectedStageId = preselectedStageId ?? _stages.first.id;
    String selectedPriority = 'medium';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('Tambah Tugas Baru'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: titleCtrl,
                  decoration: const InputDecoration(labelText: 'Judul Tugas *'),
                  autofocus: true,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: descCtrl,
                  decoration: const InputDecoration(labelText: 'Deskripsi / Detail'),
                  maxLines: 2,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: selectedStageId,
                  decoration: const InputDecoration(labelText: 'Tahapan Kolom'),
                  items: _stages.map((s) {
                    return DropdownMenuItem<int>(
                      value: s.id,
                      child: Text(s.name),
                    );
                  }).toList(),
                  onChanged: (val) {
                    if (val != null) setDialogState(() => selectedStageId = val);
                  },
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: selectedPriority,
                  decoration: const InputDecoration(labelText: 'Prioritas'),
                  items: const [
                    DropdownMenuItem(value: 'low', child: Text('Low')),
                    DropdownMenuItem(value: 'medium', child: Text('Medium')),
                    DropdownMenuItem(value: 'high', child: Text('High')),
                    DropdownMenuItem(value: 'urgent', child: Text('Urgent')),
                  ],
                  onChanged: (val) {
                    if (val != null) setDialogState(() => selectedPriority = val);
                  },
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
            ElevatedButton(
              onPressed: () async {
                final title = titleCtrl.text.trim();
                if (title.isEmpty) return;
                Navigator.pop(ctx);
                try {
                  // Find stage list_id
                  final stage = _stages.firstWhere((s) => s.id == selectedStageId);
                  await widget.kanbanRepo.createTask(
                    listId: stage.listId,
                    stageId: selectedStageId,
                    title: title,
                    description: descCtrl.text.trim(),
                    priority: selectedPriority,
                  );
                  await _fetchStages();
                } catch (_) {}
              },
              child: const Text('Simpan Tugas'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.spaceName,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            Row(
              children: [
                Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    color: _wsStatus == ReverbConnectionState.connected
                        ? const Color(0xFF10B981)
                        : (_wsStatus == ReverbConnectionState.connecting ||
                                _wsStatus == ReverbConnectionState.reconnecting
                            ? Colors.amber
                            : Colors.grey),
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 5),
                Text(
                  _wsStatus == ReverbConnectionState.connected
                      ? 'Online (Realtime)'
                      : (_wsStatus == ReverbConnectionState.connecting ||
                              _wsStatus == ReverbConnectionState.reconnecting
                          ? 'Menghubungkan...'
                          : 'Offline'),
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    color: _wsStatus == ReverbConnectionState.connected
                        ? const Color(0xFF10B981)
                        : Colors.grey,
                  ),
                ),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.people_alt_outlined),
            tooltip: 'Anggota Space',
            onPressed: _showSpaceMembersSheet,
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Muat ulang',
            onPressed: () => _fetchStages(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showCreateTaskDialog(),
        backgroundColor: const Color(0xFF00AAAD),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_task_rounded),
        label: const Text('Tambah Tugas'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _stages.isEmpty
              ? const Center(child: Text('Tidak ada tahapan kolom di Space ini.'))
              : Column(
                  children: [
                    // Top Status Segmented Chips Bar
                    Container(
                      height: 48,
                      margin: const EdgeInsets.fromLTRB(12, 8, 12, 4),
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _stages.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 8),
                        itemBuilder: (context, idx) {
                          final stage = _stages[idx];
                          final isSelected = _currentPage == idx;
                          final stageColor = _parseColor(stage.color);

                          return ChoiceChip(
                            showCheckmark: false,
                            avatar: Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                color: stageColor,
                                shape: BoxShape.circle,
                              ),
                            ),
                            label: Text(
                              '${stage.name} (${stage.tasks.length})',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                                color: isSelected ? Colors.white : Theme.of(context).textTheme.bodyMedium?.color,
                              ),
                            ),
                            selected: isSelected,
                            selectedColor: const Color(0xFF00AAAD),
                            backgroundColor: Theme.of(context).cardColor,
                            side: BorderSide(
                              color: isSelected
                                  ? const Color(0xFF00AAAD)
                                  : Colors.grey.withAlpha(50),
                            ),
                            onSelected: (selected) {
                              if (selected) {
                                setState(() => _currentPage = idx);
                                _pageController.animateToPage(
                                  idx,
                                  duration: const Duration(milliseconds: 300),
                                  curve: Curves.easeInOutCubic,
                                );
                              }
                            },
                          );
                        },
                      ),
                    ),
                    // Kanban PageView Columns
                    Expanded(
                      child: PageView.builder(
                        controller: _pageController,
                        onPageChanged: (page) {
                          setState(() => _currentPage = page);
                        },
                        itemCount: _stages.length,
                        itemBuilder: (context, index) {
                          final stage = _stages[index];
                          final stageColor = _parseColor(stage.color);

                          return Container(
                            margin: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                            decoration: BoxDecoration(
                              color: Theme.of(context).cardColor,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: Colors.grey.withAlpha(35)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                // Column Header
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 14.0, vertical: 12.0),
                                  child: Row(
                                    children: [
                                      Container(
                                        width: 10,
                                        height: 10,
                                        decoration: BoxDecoration(color: stageColor, shape: BoxShape.circle),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          stage.name,
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: Colors.grey.withAlpha(30),
                                          borderRadius: BorderRadius.circular(10),
                                        ),
                                        child: Text(
                                          '${stage.tasks.length}',
                                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                                        ),
                                      ),
                                      const SizedBox(width: 4),
                                      IconButton(
                                        icon: const Icon(Icons.add_rounded, size: 20),
                                        tooltip: 'Tambah tugas ke kolom ini',
                                        padding: EdgeInsets.zero,
                                        constraints: const BoxConstraints(),
                                        onPressed: () => _showCreateTaskDialog(preselectedStageId: stage.id),
                                      ),
                                    ],
                                  ),
                                ),
                                const Divider(height: 1),
                                // Task Cards List
                                Expanded(
                                  child: stage.tasks.isEmpty
                                      ? Center(
                                          child: Text(
                                            'Belum ada tugas di tahapan ini',
                                            style: TextStyle(fontSize: 12, color: Colors.grey.withAlpha(150)),
                                          ),
                                        )
                                      : ListView.builder(
                                          padding: const EdgeInsets.all(10),
                                          itemCount: stage.tasks.length,
                                          itemBuilder: (ctx, tIdx) {
                                            final task = stage.tasks[tIdx];
                                            return Card(
                                              elevation: 0.5,
                                              margin: const EdgeInsets.only(bottom: 8),
                                              shape: RoundedRectangleBorder(
                                                borderRadius: BorderRadius.circular(12),
                                                side: BorderSide(color: Colors.grey.withAlpha(30)),
                                              ),
                                              child: InkWell(
                                                borderRadius: BorderRadius.circular(12),
                                                onTap: () => _openTaskDetail(task),
                                                child: Padding(
                                                  padding: const EdgeInsets.all(12),
                                                  child: Column(
                                                    crossAxisAlignment: CrossAxisAlignment.start,
                                                    children: [
                                                      Row(
                                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                        children: [
                                                          Container(
                                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                            decoration: BoxDecoration(
                                                              color: _priorityColor(task.priority).withAlpha(25),
                                                              borderRadius: BorderRadius.circular(6),
                                                            ),
                                                            child: Text(
                                                              task.priority.toUpperCase(),
                                                              style: TextStyle(
                                                                fontSize: 9,
                                                                fontWeight: FontWeight.bold,
                                                                color: _priorityColor(task.priority),
                                                              ),
                                                            ),
                                                          ),
                                                          Row(
                                                            mainAxisSize: MainAxisSize.min,
                                                            children: [
                                                              PopupMenuButton<int>(
                                                                icon: const Icon(Icons.swap_horiz_rounded, size: 18, color: Colors.grey),
                                                                tooltip: 'Pindahkan Status',
                                                                padding: EdgeInsets.zero,
                                                                constraints: const BoxConstraints(),
                                                                onSelected: (targetStageId) {
                                                                  _moveTaskQuick(task, targetStageId);
                                                                },
                                                                itemBuilder: (popupCtx) {
                                                                  return _stages
                                                                      .where((s) => s.id != stage.id)
                                                                      .map((s) => PopupMenuItem<int>(
                                                                            value: s.id,
                                                                            child: Row(
                                                                              children: [
                                                                                Container(
                                                                                  width: 8,
                                                                                  height: 8,
                                                                                  decoration: BoxDecoration(
                                                                                    color: _parseColor(s.color),
                                                                                    shape: BoxShape.circle,
                                                                                  ),
                                                                                ),
                                                                                const SizedBox(width: 8),
                                                                                Text('Ke: ${s.name}', style: const TextStyle(fontSize: 13)),
                                                                              ],
                                                                            ),
                                                                          ))
                                                                      .toList();
                                                                },
                                                              ),
                                                              const SizedBox(width: 8),
                                                              const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: Colors.grey),
                                                            ],
                                                          ),
                                                        ],
                                                      ),
                                                      const SizedBox(height: 6),
                                                      Text(
                                                        task.title,
                                                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                                      ),
                                                      if (task.dueAt != null || task.checklistsCount > 0) ...[
                                                        const SizedBox(height: 8),
                                                        Row(
                                                          children: [
                                                            if (task.checklistsCount > 0) ...[
                                                              Icon(Icons.check_box_outlined, size: 14, color: Colors.grey.shade600),
                                                              const SizedBox(width: 4),
                                                              Text(
                                                                '${task.completedChecklistsCount}/${task.checklistsCount}',
                                                                style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                                              ),
                                                              const SizedBox(width: 10),
                                                            ],
                                                            if (task.dueAt != null) ...[
                                                              Icon(Icons.calendar_today_outlined, size: 13, color: Colors.grey.shade600),
                                                              const SizedBox(width: 4),
                                                              Text(
                                                                task.dueAt!,
                                                                style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                                              ),
                                                            ],
                                                          ],
                                                        ),
                                                      ],
                                                    ],
                                                  ),
                                                ),
                                              ),
                                            );
                                          },
                                        ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
    );
  }
}

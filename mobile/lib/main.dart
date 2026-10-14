import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'core/constants/api_endpoints.dart';
import 'core/network/api_client.dart';
import 'features/kanban/models/stage_model.dart';
import 'features/kanban/models/task_model.dart';
import 'features/kanban/repositories/kanban_repository.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
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
            padding: const EdgeInsets.all(28.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Image.asset(
                    'assets/images/logo.png',
                    width: 72,
                    height: 72,
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Masuk ke NexSphere',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Kelola tugas & kolaborasi proyek tim dari mana saja.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey, fontSize: 13),
                ),
                const SizedBox(height: 32),
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
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _passCtrl,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: 'Kata Sandi',
                    prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: _isLoading ? null : _login,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF00AAAD),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isLoading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Masuk Sekarang', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
                const SizedBox(height: 20),
                TextButton.icon(
                  onPressed: () => _showServerSettingsDialog(context),
                  icon: const Icon(Icons.settings_outlined, size: 16),
                  label: const Text('Pengaturan Alamat Server API', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _showServerSettingsDialog(BuildContext context) {
    final serverCtrl = TextEditingController(text: widget.apiClient.dio.options.baseUrl);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Alamat Server API'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ubah Base URL API jika diperlukan:',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: serverCtrl,
              decoration: InputDecoration(
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                isDense: true,
              ),
              style: const TextStyle(fontSize: 12),
            ),
            const SizedBox(height: 12),
            const Text(
              'Preset Server:\n- Live: https://projectmanagement.nexsphere.web.id/api/v1\n- Emulator Android: http://10.0.2.2:8000/api/v1',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              widget.apiClient.setBaseUrl('https://projectmanagement.nexsphere.web.id/api/v1');
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Server di-reset ke Live Produksi')),
              );
            },
            child: const Text('Reset Live'),
          ),
          ElevatedButton(
            onPressed: () {
              final newUrl = serverCtrl.text.trim();
              if (newUrl.isNotEmpty) {
                widget.apiClient.setBaseUrl(newUrl);
                Navigator.pop(ctx);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Server diubah ke: $newUrl')),
                );
              }
            },
            child: const Text('Simpan'),
          ),
        ],
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

class _WorkspaceScreenState extends State<WorkspaceScreen> {
  bool _isLoading = true;
  String _activeTab = 'spaces'; // 'dashboard', 'spaces', 'my_tasks', 'activity_logs'

  List<dynamic> _workspaces = [];
  Map<String, dynamic>? _selectedWorkspace;
  List<dynamic> _spaces = [];
  String? _userName;
  String? _userEmail;

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

  // Timeline
  List<dynamic> _timelineTasks = [];
  bool _isTimelineLoading = false;
  int? _timelineSpaceId;

  @override
  void initState() {
    super.initState();
    _loadInitialData();
  }

  Future<void> _loadInitialData() async {
    setState(() => _isLoading = true);
    try {
      final meRes = await widget.apiClient.dio.get(ApiEndpoints.me);
      _userName = meRes.data['user']?['name'];
      _userEmail = meRes.data['user']?['email'];

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

  void _switchTab(String tab) {
    setState(() => _activeTab = tab);
    Navigator.pop(context); // Close Drawer
    if (tab == 'dashboard') {
      _loadDashboard();
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Image.asset('assets/images/logo.png', width: 28, height: 28),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                _getAppTitle(),
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, size: 22),
            tooltip: 'Segarkan data',
            onPressed: () {
              if (_activeTab == 'dashboard') {
                _loadDashboard();
              } else if (_activeTab == 'timeline') {
                if (_timelineSpaceId != null) _loadTimeline(_timelineSpaceId!);
              } else if (_activeTab == 'my_tasks') {
                _loadMyTasks();
              } else if (_activeTab == 'activity_logs') {
                _loadActivityLogs();
              } else {
                _loadInitialData();
              }
            },
          ),
        ],
      ),
      drawer: Drawer(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            UserAccountsDrawerHeader(
              decoration: const BoxDecoration(
                color: Color(0xFF00AAAD),
              ),
              currentAccountPicture: CircleAvatar(
                backgroundColor: Colors.white,
                child: Padding(
                  padding: const EdgeInsets.all(4.0),
                  child: Image.asset('assets/images/logo.png'),
                ),
              ),
              accountName: Text(
                _userName ?? 'Pengguna NexSphere',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              accountEmail: Text(
                _userEmail ?? 'nexsphere@user.com',
                style: const TextStyle(fontSize: 12),
              ),
            ),
            if (_selectedWorkspace != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFF00AAAD).withAlpha(20),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.business_rounded, size: 16, color: Color(0xFF00AAAD)),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _selectedWorkspace!['name'] as String,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF00AAAD),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            const Padding(
              padding: EdgeInsets.only(left: 16, top: 12, bottom: 4),
              child: Text(
                'RINGKASAN & PROYEK',
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.grey, letterSpacing: 1),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.dashboard_rounded),
              title: const Text('Dashboard', style: TextStyle(fontWeight: FontWeight.w600)),
              selected: _activeTab == 'dashboard',
              selectedColor: const Color(0xFF00AAAD),
              selectedTileColor: const Color(0xFF00AAAD).withAlpha(20),
              onTap: () => _switchTab('dashboard'),
            ),
            ListTile(
              leading: const Icon(Icons.folder_open_rounded),
              title: const Text('Spaces & Projects', style: TextStyle(fontWeight: FontWeight.w600)),
              selected: _activeTab == 'spaces',
              selectedColor: const Color(0xFF00AAAD),
              selectedTileColor: const Color(0xFF00AAAD).withAlpha(20),
              trailing: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.grey.withAlpha(30),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text('${_spaces.length}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
              ),
              onTap: () => _switchTab('spaces'),
            ),
            ListTile(
              leading: const Icon(Icons.view_timeline_rounded),
              title: const Text('Timeline Proyek', style: TextStyle(fontWeight: FontWeight.w600)),
              selected: _activeTab == 'timeline',
              selectedColor: const Color(0xFF00AAAD),
              selectedTileColor: const Color(0xFF00AAAD).withAlpha(20),
              onTap: () => _switchTab('timeline'),
            ),
            ListTile(
              leading: const Icon(Icons.check_box_outlined),
              title: const Text('Tugas Saya (My Tasks)', style: TextStyle(fontWeight: FontWeight.w600)),
              selected: _activeTab == 'my_tasks',
              selectedColor: const Color(0xFF00AAAD),
              selectedTileColor: const Color(0xFF00AAAD).withAlpha(20),
              onTap: () => _switchTab('my_tasks'),
            ),
            const Padding(
              padding: EdgeInsets.only(left: 16, top: 12, bottom: 4),
              child: Text(
                'KOLABORASI',
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.grey, letterSpacing: 1),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.history_rounded),
              title: const Text('Activity Logs', style: TextStyle(fontWeight: FontWeight.w600)),
              selected: _activeTab == 'activity_logs',
              selectedColor: const Color(0xFF00AAAD),
              selectedTileColor: const Color(0xFF00AAAD).withAlpha(20),
              onTap: () => _switchTab('activity_logs'),
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.settings_outlined),
              title: const Text('Pengaturan Server API'),
              onTap: () {
                Navigator.pop(context);
                _showServerSettingsDialog(context);
              },
            ),
            ListTile(
              leading: const Icon(Icons.logout_rounded, color: Colors.red),
              title: const Text('Keluar (Logout)', style: TextStyle(color: Colors.red)),
              onTap: () {
                Navigator.pop(context);
                _logout();
              },
            ),
          ],
        ),
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
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _buildCurrentContent(),
    );
  }

  void _showServerSettingsDialog(BuildContext context) {
    final serverCtrl = TextEditingController(text: widget.apiClient.dio.options.baseUrl);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Alamat Server API'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ubah Base URL API jika diperlukan:',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: serverCtrl,
              decoration: InputDecoration(
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                isDense: true,
              ),
              style: const TextStyle(fontSize: 12),
            ),
            const SizedBox(height: 12),
            const Text(
              'Preset Server:\n- Live: https://projectmanagement.nexsphere.web.id/api/v1\n- Emulator: http://10.0.2.2:8000/api/v1',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              widget.apiClient.setBaseUrl('https://projectmanagement.nexsphere.web.id/api/v1');
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Server di-reset ke Live Produksi')),
              );
            },
            child: const Text('Reset Live'),
          ),
          ElevatedButton(
            onPressed: () {
              final newUrl = serverCtrl.text.trim();
              if (newUrl.isNotEmpty) {
                widget.apiClient.setBaseUrl(newUrl);
                Navigator.pop(ctx);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Server diubah ke: $newUrl')),
                );
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
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

  // 1. DASHBOARD VIEW
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

    return RefreshIndicator(
      onRefresh: _loadDashboard,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Completion rate card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF00AAAD), Color(0xFF007A7C)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Tingkat Penyelesaian Proyek',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      '$rate%',
                      style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold),
                    ),
                    Text(
                      '$compTk / $totalTk Selesai',
                      style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
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
                    minHeight: 8,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const Text('Metrik Ringkasan:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 1.4,
            children: [
              _buildStatCard('Total Workspaces', '$totalWs', Icons.business_rounded, const Color(0xFF00AAAD)),
              _buildStatCard('Total Spaces', '$totalSp', Icons.folder_open_rounded, Colors.indigo),
              _buildStatCard('Tugas Aktif', '$actTk', Icons.pending_actions_rounded, Colors.orange),
              _buildStatCard('Tugas Selesai', '$compTk', Icons.check_circle_rounded, Colors.green),
            ],
          ),
          const SizedBox(height: 24),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Spaces Cepat:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
              TextButton(
                onPressed: () => _switchTab('spaces'),
                child: const Text('Lihat Semua'),
              ),
            ],
          ),
          ..._spaces.take(3).map((sp) {
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: const Color(0xFF00AAAD).withAlpha(25),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.folder_rounded, color: Color(0xFF00AAAD), size: 20),
              ),
              title: Text(sp['name'] as String, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
              subtitle: Text(sp['description'] as String? ?? 'Space proyek', maxLines: 1, overflow: TextOverflow.ellipsis),
              trailing: const Icon(Icons.chevron_right_rounded, size: 18),
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
            );
          }),
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
        padding: const EdgeInsets.all(16),
        children: [
          // Workspace header with CRUD controls
          Row(
            children: [
              const Text('Workspace:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
              const Spacer(),
              IconButton(
                icon: const Icon(Icons.add_business_rounded, size: 20, color: Color(0xFF00AAAD)),
                tooltip: 'Buat Workspace Baru',
                onPressed: _showCreateWorkspaceDialog,
              ),
              if (_selectedWorkspace != null) ...[
                IconButton(
                  icon: const Icon(Icons.edit_outlined, size: 18, color: Colors.grey),
                  tooltip: 'Ubah Nama Workspace',
                  onPressed: () => _showEditWorkspaceDialog(
                    _selectedWorkspace!['id'] as int,
                    _selectedWorkspace!['name'] as String,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.delete_outline_rounded, size: 18, color: Colors.red),
                  tooltip: 'Hapus Workspace',
                  onPressed: () => _confirmDeleteWorkspace(
                    _selectedWorkspace!['id'] as int,
                    _selectedWorkspace!['name'] as String,
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 4),
          if (_workspaces.isNotEmpty)
            DropdownButtonFormField<int>(
              initialValue: _selectedWorkspace?['id'] as int?,
              decoration: InputDecoration(
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              ),
              items: _workspaces.map((w) {
                return DropdownMenuItem<int>(
                  value: w['id'] as int,
                  child: Text(w['name'] as String, style: const TextStyle(fontWeight: FontWeight.w600)),
                );
              }).toList(),
              onChanged: (id) {
                if (id != null) {
                  final selected = _workspaces.firstWhere((w) => w['id'] == id);
                  setState(() => _selectedWorkspace = selected);
                  _loadSpacesForWorkspace(id);
                }
              },
            ),
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Daftar Space Proyek:', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
              TextButton.icon(
                onPressed: _showCreateSpaceDialog,
                icon: const Icon(Icons.add_rounded, size: 16),
                label: const Text('Tambah Space', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (_spaces.isEmpty)
            Container(
              padding: const EdgeInsets.all(24),
              alignment: Alignment.center,
              child: Column(
                children: [
                  const Icon(Icons.folder_open_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 8),
                  const Text('Belum ada Space proyek di workspace ini.', style: TextStyle(color: Colors.grey, fontSize: 13)),
                  const SizedBox(height: 12),
                  ElevatedButton.icon(
                    onPressed: _showCreateSpaceDialog,
                    icon: const Icon(Icons.add),
                    label: const Text('Buat Space Pertama'),
                  ),
                ],
              ),
            )
          else
            ..._spaces.map((sp) {
              return Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: BorderSide(color: Colors.grey.withAlpha(40)),
                ),
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: const Color(0xFF00AAAD).withAlpha(30),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.view_kanban_rounded, color: Color(0xFF00AAAD), size: 24),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: InkWell(
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
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                sp['name'] as String,
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                sp['description'] as String? ?? 'Space proyek tim',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: Colors.grey, fontSize: 12),
                              ),
                            ],
                          ),
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
                label: const Text('Semua', style: TextStyle(fontSize: 11)),
                selected: _myTasksFilter == 'all',
                onSelected: (_) => setState(() => _myTasksFilter = 'all'),
              ),
              const SizedBox(width: 8),
              ChoiceChip(
                label: const Text('Aktif', style: TextStyle(fontSize: 11)),
                selected: _myTasksFilter == 'active',
                onSelected: (_) => setState(() => _myTasksFilter = 'active'),
              ),
              const SizedBox(width: 8),
              ChoiceChip(
                label: const Text('Selesai', style: TextStyle(fontSize: 11)),
                selected: _myTasksFilter == 'completed',
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
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadMyTasks,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: filtered.length,
                    itemBuilder: (ctx, idx) {
                      final t = filtered[idx];
                      final priority = (t['priority'] as String?) ?? 'medium';
                      final pColor = _priorityColor(priority);
                      final stageName = t['stage']?['name'] as String? ?? 'Tahapan';
                      final isDone = t['stage']?['is_done'] as bool? ?? false;

                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                          side: BorderSide(color: Colors.grey.withAlpha(35)),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: pColor.withAlpha(25),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      priority.toUpperCase(),
                                      style: TextStyle(color: pColor, fontSize: 10, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: (isDone ? Colors.green : Colors.grey).withAlpha(25),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      stageName,
                                      style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                        color: isDone ? Colors.green : null,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                t['title'] as String,
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  decoration: isDone ? TextDecoration.lineThrough : null,
                                ),
                              ),
                              if (t['description'] != null && (t['description'] as String).isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  t['description'] as String,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                                ),
                              ],
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  if (t['due_at'] != null) ...[
                                    const Icon(Icons.calendar_today_outlined, size: 12, color: Colors.grey),
                                    const SizedBox(width: 4),
                                    Text(
                                      t['due_at'] as String,
                                      style: const TextStyle(fontSize: 11, color: Colors.grey),
                                    ),
                                    const SizedBox(width: 12),
                                  ],
                                  if (t['checklists_count'] != null && (t['checklists_count'] as int) > 0) ...[
                                    const Icon(Icons.check_box_outlined, size: 13, color: Colors.grey),
                                    const SizedBox(width: 4),
                                    Text(
                                      '${t['completed_checklists_count'] ?? 0}/${t['checklists_count']}',
                                      style: const TextStyle(fontSize: 11, color: Colors.grey),
                                    ),
                                  ],
                                ],
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
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadActivityLogs,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _activityLogs.length,
        separatorBuilder: (_, __) => const Divider(height: 16),
        itemBuilder: (ctx, idx) {
          final log = _activityLogs[idx];
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                margin: const EdgeInsets.only(top: 2),
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: const Color(0xFF00AAAD).withAlpha(20),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.bolt_rounded, size: 18, color: Color(0xFF00AAAD)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      log['description'] as String? ?? 'Aktivitas sistem',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      log['created_at'] as String? ?? '',
                      style: const TextStyle(fontSize: 11, color: Colors.grey),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
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

  @override
  void initState() {
    super.initState();
    _fetchStages();
  }

  Future<void> _fetchStages() async {
    setState(() => _isLoading = true);
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
      if (mounted) setState(() => _isLoading = false);
    }
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

  void _showTaskDetailDialog(TaskModel task) {
    final titleCtrl = TextEditingController(text: task.title);
    final descCtrl = TextEditingController(text: task.description ?? '');
    String selectedPriority = task.priority;
    int selectedStageId = task.stageId;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: Row(
            children: [
              Expanded(
                child: Text('Edit Tugas #${task.id}', style: const TextStyle(fontSize: 16)),
              ),
              IconButton(
                icon: const Icon(Icons.delete_outline_rounded, color: Colors.red),
                tooltip: 'Hapus Tugas',
                onPressed: () async {
                  Navigator.pop(ctx);
                  _confirmDeleteTask(task.id);
                },
              ),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: titleCtrl,
                  decoration: const InputDecoration(labelText: 'Judul Tugas'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: descCtrl,
                  decoration: const InputDecoration(labelText: 'Deskripsi'),
                  maxLines: 3,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: selectedStageId,
                  decoration: const InputDecoration(labelText: 'Pindahkan Tahapan'),
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
                if (task.checklists.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  const Text('Checklists / Subtask:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 6),
                  ...task.checklists.map((chk) {
                    return CheckboxListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      value: chk.isCompleted,
                      title: Text(chk.title, style: TextStyle(fontSize: 12, decoration: chk.isCompleted ? TextDecoration.lineThrough : null)),
                      onChanged: (val) async {
                        await widget.kanbanRepo.toggleChecklist(task.id, chk.id);
                        if (ctx.mounted) Navigator.pop(ctx);
                        if (mounted) _fetchStages();
                      },
                    );
                  }),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
            ElevatedButton(
              onPressed: () async {
                final newTitle = titleCtrl.text.trim();
                if (newTitle.isEmpty) return;
                Navigator.pop(ctx);

                try {
                  // If stage changed, move task
                  if (selectedStageId != task.stageId) {
                    await widget.kanbanRepo.moveTask(
                      taskId: task.id,
                      stageId: selectedStageId,
                      position: 1,
                    );
                  }

                  // Update task details
                  await widget.kanbanRepo.updateTask(
                    taskId: task.id,
                    title: newTitle,
                    description: descCtrl.text.trim(),
                    priority: selectedPriority,
                  );

                  await _fetchStages();
                } catch (_) {}
              },
              child: const Text('Simpan Perubahan'),
            ),
          ],
        ),
      ),
    );
  }

  void _confirmDeleteTask(int taskId) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Tugas?'),
        content: const Text('Tugas ini akan dihapus secara permanen.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                await widget.kanbanRepo.deleteTask(taskId);
                await _fetchStages();
              } catch (_) {}
            },
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.spaceName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Muat ulang',
            onPressed: _fetchStages,
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
              : ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                  itemCount: _stages.length,
                  itemBuilder: (context, index) {
                    final stage = _stages[index];
                    final stageColor = _parseColor(stage.color);

                    return Container(
                      width: 280,
                      margin: const EdgeInsets.only(right: 12),
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
                            padding: const EdgeInsets.all(14.0),
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
                                  icon: const Icon(Icons.add_rounded, size: 18),
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
                                      'Belum ada tugas',
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
                                          onTap: () => _showTaskDetailDialog(task),
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
                                                    const Icon(Icons.edit_note_rounded, size: 16, color: Colors.grey),
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
    );
  }
}
